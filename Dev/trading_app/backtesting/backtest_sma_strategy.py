import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import matplotlib.dates as mdates
import os
from datetime import datetime
import sys
import argparse

# Define root, analysis, data, and plots directories
root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
analysis_dir = os.path.join(root_dir, 'analysis')
data_dir = os.path.join(root_dir, 'data')
plots_dir = os.path.join(root_dir, 'backtesting', 'plots')
sys.path.append(analysis_dir)
sys.path.append(data_dir)

# Create the 'plots' directory if it doesn't exist
if not os.path.exists(plots_dir):
    os.makedirs(plots_dir)

# Import custom modules. Handle ModuleNotFoundError for clean exit if custom modules are missing.
try:
    from fetch_stock_data import fetch_data
    import indicators
except ModuleNotFoundError as e:
    print(f"Failed to import a module: {e}")
    sys.exit(1)

def apply_sma_strategy(data, short_window, long_window, cooldown):
    data = data.copy()
    sma_short_column = f'SMA_{short_window}'
    sma_long_column = f'SMA_{long_window}'
    signal_column = f'Signal_{short_window}_{long_window}'
    position_column = f'Position_{short_window}_{long_window}'

    # Calculate SMAs
    data[sma_short_column] = data['close'].rolling(window=short_window, min_periods=1).mean()
    data[sma_long_column] = data['close'].rolling(window=long_window, min_periods=1).mean()

    # Generate signals: 1 for buy, -1 for sell, 0 for hold
    data[signal_column] = 0
    data.loc[data[sma_short_column] > data[sma_long_column], signal_column] = 1
    data.loc[data[sma_short_column] < data[sma_long_column], signal_column] = -1

    # Initialize the 'Position' column to match the first signal
    data[position_column] = data[signal_column].iloc[0]

    cooldown_counter = 0  # Initialize cooldown counter

    # Apply cooldown logic
    for i in range(1, len(data)):
        if cooldown_counter > 0:
            cooldown_counter -= 1
        if data.loc[i, signal_column] != data.loc[i - 1, signal_column] and cooldown_counter == 0:
            cooldown_counter = cooldown
            data.loc[i, position_column] = data.loc[i, signal_column]
        else:
            data.loc[i, position_column] = data.loc[i - 1, position_column]

    # Fill initial values in 'Position' if they are 0, because we cannot trade without signals
    initial_positions = data[position_column] == 0
    if initial_positions.any():
        initial_signal = data.loc[~initial_positions, position_column].iloc[0]
        data.loc[initial_positions, position_column] = initial_signal

    # Debug: Print signals and positions for the last few data points
    print(f"Signals and positions for strategy {short_window}_{long_window}:")
    print(data[['date', 'close', signal_column, position_column]].tail(10))

    return data, signal_column, position_column



def sma_strategy(data, short_periods, long_periods, cooldown):
    strategies = []
    for short_period in short_periods:
        for long_period in long_periods:
            if short_period < long_period:
                strategy_data, signal_column, position_column = apply_sma_strategy(data, short_period, long_period, cooldown)
                strategies.append({
                    'short_period': short_period,
                    'long_period': long_period,
                    'data': strategy_data,
                    'signal_column': signal_column,
                    'position_column': position_column
                })
    return strategies


def calculate_performance_metrics(data, position_column):
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data.")

    if position_column not in data.columns:
        raise KeyError(f"'{position_column}' column not found in data.")


    # Calculate daily and strategy returns
    data['daily_return'] = np.nan_to_num(data['close'].pct_change())
    data['strategy_return'] = np.nan_to_num(data['daily_return'] * data[position_column].shift())

    # Calculate Sharpe Ratio with robust checks
    mean_return = data['strategy_return'].mean()
    std_dev = data['strategy_return'].std()

    # Check for non-zero and non-NaN standard deviation
    if std_dev > 0 and not np.isnan(std_dev):
        sharpe_ratio = mean_return / std_dev * np.sqrt(252)
    else:
        sharpe_ratio = 0

    sharpe_ratio = np.nan_to_num(sharpe_ratio)  # Convert NaNs or infinite values to zero

    total_return = data['strategy_return'].cumsum().iloc[-1]

    trades = data[position_column].diff().abs()
    wins = ((data['strategy_return'] > 0) & trades).sum()
    total_trades = trades.sum()
    win_rate = wins / total_trades if total_trades > 0 else 0

    gross_profit = np.nan_to_num(data[data['strategy_return'] > 0]['strategy_return'].sum())
    gross_loss = np.nan_to_num(-data[data['strategy_return'] < 0]['strategy_return'].sum())
    profit_factor = np.nan_to_num(gross_profit / gross_loss) if gross_loss > 0 else 0

    sortino_ratio = np.nan_to_num(mean_return / data[data['strategy_return'] < 0]['strategy_return'].std() * np.sqrt(252))

    expectancy = (win_rate * gross_profit) - ((1 - win_rate) * gross_loss)

    metrics = {
        'total_return': total_return,
        'win_rate': win_rate,
        'profit_factor': profit_factor,
        'sharpe_ratio': sharpe_ratio,
        'sortino_ratio': sortino_ratio,
        'expectancy': expectancy
    }

    return metrics

def plot_strategy(data, symbol, strategy_name, metrics, short_period, long_period, output_dir):
    plt.figure(figsize=(15, 8))
    date_column = 'date'  # Assuming 'date' is the name of your date column
    data[date_column] = pd.to_datetime(data[date_column])

    # Plotting price line
    plt.plot(data[date_column], data['close'], label='Price', alpha=0.7, color='lightblue')

    # Adjust the position column name based on the strategy being plotted
    if strategy_name == 'Consensus_Strategy':
        position_column = 'Consensus_Position'
    elif short_period is not None and long_period is not None:
        position_column = f'Position_{short_period}_{long_period}'
        sma_short_column = f'SMA_{short_period}'
        sma_long_column = f'SMA_{long_period}'
        # Plot SMA lines for individual strategies
        plt.plot(data[date_column], data[sma_short_column], label=f'SMA {short_period}', alpha=0.7, linestyle='--')
        plt.plot(data[date_column], data[sma_long_column], label=f'SMA {long_period}', alpha=0.7, linestyle='--')
    else:
        # Default to 'Position' if no specific column is found (for individual strategies)
        position_column = 'Position'

    
    position_column = f"Position_{short_period}_{long_period}" if short_period and long_period else 'Consensus_Position'

    if position_column not in data.columns:
        print(f"'{position_column}' column not found for {strategy_name}. Skipping plot.")
        return None


    # Plot buy and sell signals
    buys = data[data[position_column] == 1]
    sells = data[data[position_column] == -1]
    plt.scatter(buys[date_column], buys['close'], label='Buy Signal', marker='^', color='green', alpha=0.7)
    plt.scatter(sells[date_column], sells['close'], label='Sell Signal', marker='v', color='red', alpha=0.7)

    # Adding metrics to the plot with conditional coloring
    vertical_position = 0.95
    for key, value in metrics.items():
        color = 'green' if key == 'win_rate' and value > 0 else 'red'
        plt.text(0.01, vertical_position, f"{key}: {value:.2f}", transform=plt.gca().transAxes, verticalalignment='top', color=color, fontsize=10)
        vertical_position -= 0.03

    # Setting titles and labels
    plt.title(f"{symbol} - {strategy_name}")
    plt.xlabel('Date')
    plt.ylabel('Price')
    plt.legend(loc='best')
    plt.grid(True)
    plt.gca().xaxis.set_major_formatter(mdates.DateFormatter('%Y-%m-%d'))
    plt.xticks(rotation=45)

    # Save plot to file
    filename = f"{symbol}_{strategy_name}_{pd.Timestamp.now().strftime('%Y%m%d%H%M%S')}.png"
    filepath = os.path.join(output_dir, filename)
    plt.savefig(filepath, dpi=300)
    plt.close()

    return filepath



def consensus_strategy(data, strategies, global_cooldown):
    data['Consensus_Signal'] = 0
    consensus_count = len(strategies)

    for strategy in strategies:
        signal_column = strategy['signal_column']
        if signal_column not in data.columns:
            raise KeyError(f"Expected column {signal_column} not found in data.")
        data['Consensus_Signal'] += data[signal_column]

    # Determine consensus signal based on majority rule
    data['Consensus_Signal'] = np.where(
        data['Consensus_Signal'] > consensus_count // 2, 1,
        np.where(data['Consensus_Signal'] < -consensus_count // 2, -1, 0)
    )

    # Ttie-breaking mechanism: favor the previous signal in case of a tie
    data['Consensus_Signal'] = np.where(
        (data['Consensus_Signal'] == 0) & (data['Consensus_Signal'].shift() != 0),
        data['Consensus_Signal'].shift(), 
        data['Consensus_Signal']
    )

    # Apply cooldown logic
    data['Consensus_Position'] = 0
    cooldown_counter = global_cooldown  # Start with cooldown in effect to prevent early trades

    for i in range(1, len(data)):
        if cooldown_counter > 0:
            cooldown_counter -= 1
        elif data['Consensus_Signal'].iloc[i] != 0 and cooldown_counter == 0:
            data.at[i, 'Consensus_Position'] = data['Consensus_Signal'].iloc[i]
            cooldown_counter = global_cooldown  # Reset cooldown

    return data


def backtest_sma(symbol, start_date, end_date, short_periods, long_periods, cooldown):
    # Fetch stock data
    data = fetch_data(symbol, start_date, end_date)
    
    # Initialize a list to hold strategy details
    strategies = []
    
    # Loop through all combinations of short and long periods
    for short_period in short_periods:
        for long_period in long_periods:
            if short_period < long_period:
                # Unpack only two values since the function now returns two values
                strategy_data, signal_column, position_column = apply_sma_strategy(data.copy(), short_period, long_period, cooldown)

                # Add the signal column to the main DataFrame
                data[f"Signal_{short_period}_{long_period}"] = strategy_data[signal_column]
                
                # Calculate the 'Position' based on the 'Signal' column
                strategy_data['Position'] = strategy_data[signal_column].diff().fillna(0)
                
                # Append the strategy details to the strategies list
                strategies.append({
                    'short_period': short_period,
                    'long_period': long_period,
                    'signal_column': f"Signal_{short_period}_{long_period}",
                    'position_column': 'Position',
                    'data': strategy_data
                })

    # Loop through each strategy and incorporate their signals into the main data DataFrame
    for strategy in strategies:
        signal_column = strategy['signal_column']
        strategy_data = strategy['data']
        
        # Make sure the position column is named consistently with how it's used in plotting
        position_column_name = f"Position_{strategy['short_period']}_{strategy['long_period']}"
        data[position_column_name] = strategy_data['Position']

        # Debug: Print signal and position for the last few data points before plotting
        print(f"Data before plotting for strategy {strategy['short_period']}_{strategy['long_period']}:")
        print(strategy_data[['date', 'close', signal_column, 'Position']].tail(10))

        # Calculate performance metrics for each strategy
        metrics = calculate_performance_metrics(strategy_data, 'Position')

        # Plot and save the strategy results
        plot_filepath = plot_strategy(strategy_data, symbol, f"SMA_{strategy['short_period']}_{strategy['long_period']}", metrics, strategy['short_period'], strategy['long_period'], plots_dir)
        print(f"Plot saved to {plot_filepath}")

    # Now apply and evaluate the consensus strategy
    consensus_data = consensus_strategy(data, strategies, cooldown)
    consensus_metrics = calculate_performance_metrics(consensus_data, 'Consensus_Position')
    consensus_plot_filepath = plot_strategy(consensus_data, symbol, 'Consensus_Strategy', consensus_metrics, None, None, plots_dir)
    print(f"Consensus plot saved to {consensus_plot_filepath}")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Backtest SMA Strategy')
    parser.add_argument('symbol', type=str, help='Stock symbol to backtest')
    args = parser.parse_args()
    symbol = args.symbol
    start_date, end_date = '2022-01-01', '2023-04-01'
    short_periods, long_periods = [10, 20], [50, 100]
    cooldown = 100  # Example cooldown period


    backtest_sma(symbol, start_date, end_date, short_periods, long_periods, cooldown)
