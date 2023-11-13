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


def sma_strategy(data, short_period, long_period, cooldown):
    # Copying data and adding indicators (if any)
    data = indicators.add_indicators(data.copy())

    # Calculate SMAs
    data['SMA_short'] = data['close'].rolling(window=short_period).mean()
    data['SMA_long'] = data['close'].rolling(window=long_period).mean()

    data['SMA_short'].bfill(inplace=True)  # Backward fill for SMA_short
    data['SMA_long'].bfill(inplace=True)   # Backward fill for SMA_long


 
    # Generate signals based on SMA crossovers
    signal_column = f'Signal_{short_period}_{long_period}'
    data[signal_column] = np.where(data['SMA_short'] > data['SMA_long'], 1, 
                                  np.where(data['SMA_short'] < data['SMA_long'], -1, 0))

    # Initialize cooldown column
    cooldown_column = f'Cooldown_{short_period}_{long_period}'
    data[cooldown_column] = 0

    # Apply cooldown logic
    for i in range(1, len(data)):
        if data.at[i - 1, cooldown_column] > 0:
            data.at[i, cooldown_column] = data.at[i - 1, cooldown_column] - 1
            data.at[i, signal_column] = 0
        elif data.at[i, signal_column] != 0:
            data.at[i, cooldown_column] = cooldown

    # Debugging: Print sample SMA values and generated signals
    print(data[['close', 'SMA_short', 'SMA_long', signal_column]].head(10))

 
    position_column = f'Position_{short_period}_{long_period}'
    data[position_column] = data[signal_column].diff()

    return data, signal_column, position_column, cooldown_column


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
    
    # Inside plot_strategy
    if strategy_name == 'Consensus_Strategy':
        print("Plotting Consensus Strategy...")
        print(data[['date', 'Consensus_Position']].dropna().head())  # Check first few non-null entries


    plt.figure(figsize=(15, 8))
    date_column = data.columns[0]
    data[date_column] = pd.to_datetime(data[date_column])

    # Plotting price line
    plt.plot(data[date_column], data['close'], label='Price', alpha=0.7, color='lightblue')

    # Plot SMA lines if applicable
    if short_period and long_period:
        plt.plot(data[date_column], data[f'SMA_{short_period}'], label=f'SMA {short_period}', alpha=0.7)
        plt.plot(data[date_column], data[f'SMA_{long_period}'], label=f'SMA {long_period}', alpha=0.7)
        position_column = f'Position_{short_period}_{long_period}'  # Use position column for plotting
    else:
        position_column = 'Consensus_Position'  # For consensus strategy

    if strategy_name == 'Consensus_Strategy':
        print(data[['date', 'Consensus_Position']].head())  # Print first few entries
   

    # Plot buy and sell signals
    plt.scatter(data[date_column][data[position_column] == 1], data['close'][data[position_column] == 1], label='Buy Signal', marker='^', color='green')
    plt.scatter(data[date_column][data[position_column] == -1], data['close'][data[position_column] == -1], label='Sell Signal', marker='v', color='red')

    # Adding metrics to the plot with conditional coloring
    vertical_position = 0.99
    for key, value in metrics.items():
        color = 'green' if value > 0 else 'red'
        plt.text(0.01, vertical_position, f"{key}: {value:.2f}", transform=plt.gca().transAxes, verticalalignment='top', color=color, fontsize=14)
        vertical_position -= 0.05

    # Setting titles and labels
    plt.title(f"{symbol} - {strategy_name}")
    plt.xlabel('Date')
    plt.ylabel('Price')
    plt.legend()
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
    # Initialize consensus signal column
    data['Consensus_Signal'] = 0

    # Aggregate signals from all strategies

    # Diagnostic code to examine individual strategy signals
    for strategy in strategies:
        signal_column = strategy['signal_column']
        print(f"Signal Column: {signal_column}")
        print(data[[signal_column]].dropna().head(10))  # Display the first 10 non-null signal values

        # Add 1 for positive signals, subtract 1 for negative signals
        data['Consensus_Signal'] += np.where(data[signal_column] > 0, 1, 
                                             np.where(data[signal_column] < 0, -1, 0))

    # Determine consensus action based on majority
    consensus_threshold = len(strategies) // 2
    data['Consensus_Signal'] = np.where(data['Consensus_Signal'] > consensus_threshold, 1, 
                                        np.where(data['Consensus_Signal'] < -consensus_threshold, -1, 0))

    # Apply cooldown logic
    cooldown_counter = 0
    for i in range(len(data)):
        if cooldown_counter > 0:
            data.at[i, 'Consensus_Signal'] = 0
            cooldown_counter -= 1
        elif data.at[i, 'Consensus_Signal'] != 0:
            cooldown_counter = global_cooldown

    # Calculate consensus positions to reflect valid trading actions
    data['Consensus_Position'] = data['Consensus_Signal'].diff()

    return data






def backtest_sma(symbol, start_date, end_date, short_periods, long_periods, cooldown):
    """
    Backtest SMA strategies and consensus strategy on given stock data.

    Parameters:
    symbol (str): Stock symbol to backtest.
    start_date (str): Start date of the backtesting period.
    end_date (str): End date of the backtesting period.
    short_periods (list): List of short SMA periods.
    long_periods (list): List of long SMA periods.
    cooldown (int): Cooldown period for the consensus strategy.

    """
    # Fetch stock data
    data = fetch_data(symbol, start_date, end_date)
    
    strategies = []
    for short_period in short_periods:
        for long_period in long_periods:
            if short_period < long_period:
                # Get SMA strategy data and cooldown columns
                sma_data, signal_column, position_column, cooldown_column = sma_strategy(data, short_period, long_period, cooldown)
                data[signal_column] = sma_data[signal_column]
                data[position_column] = sma_data[position_column]
                data[cooldown_column] = sma_data[cooldown_column]


                # Append strategy info to strategies list
                strategies.append({'signal_column': signal_column, 
                                   'position_column': position_column, 
                                   'cooldown_column': cooldown_column})
                print(f"Strategy appended: {strategies[-1]}")  # Print the last appended strategy

                # Print DataFrame columns to check if the cooldown column is present
                print(f"Current DataFrame columns: {data.columns.tolist()}")

                # Calculate performance metrics
                data['daily_return'] = np.nan_to_num(data['close'].pct_change())
                data['strategy_return'] = np.nan_to_num(data['daily_return'] * data[position_column].shift())  
                metrics = calculate_performance_metrics(data, position_column)


                # Plot and save the strategy results
                plot_filepath = plot_strategy(sma_data, symbol, f'SMA_{short_period}_{long_period}', metrics, short_period, long_period, plots_dir)
                print(f"Plot saved to {plot_filepath}")

    # Apply and evaluate the consensus strategy
    print("Evaluating consensus strategy...")
    consensus_data = consensus_strategy(data.copy(), strategies, cooldown)
    consensus_metrics = calculate_performance_metrics(consensus_data, 'Consensus_Position')
    consensus_plot_filepath = plot_strategy(consensus_data, symbol, 'Consensus_Strategy', consensus_metrics, None, None, plots_dir)
    print(f"Consensus plot saved to {consensus_plot_filepath}")


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Backtest SMA Strategy')
    parser.add_argument('symbol', type=str, help='Stock symbol to backtest')
    args = parser.parse_args()
    symbol = args.symbol
    start_date, end_date = '2022-04-01', '2023-04-01'
    short_periods, long_periods = [10, 20], [50, 100]
    cooldown = 50  # Example cooldown period

    backtest_sma(symbol, start_date, end_date, short_periods, long_periods, cooldown)
