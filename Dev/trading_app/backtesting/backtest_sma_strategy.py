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

def sma_strategy(data, short_period, long_period):
    """
    Implement the SMA strategy for a given stock data.

    Parameters:
    data (DataFrame): DataFrame containing stock price data.
    short_period (int): Period for the short-term SMA.
    long_period (int): Period for the long-term SMA.

    Returns:
    DataFrame: DataFrame with additional columns for SMA values, signals, and positions.
    """
    # Check if 'close' column exists in the data
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data.")

    # Calculate the short-term and long-term SMA
    data[f'SMA_{short_period}'] = data['close'].rolling(window=short_period).mean()
    data[f'SMA_{long_period}'] = data['close'].rolling(window=long_period).mean()

    # Generate buy/sell signals based on SMA crossover
    data['Signal'] = np.where(data[f'SMA_{short_period}'] > data[f'SMA_{long_period}'], 1, 
                             np.where(data[f'SMA_{short_period}'] < data[f'SMA_{long_period}'], -1, 0))

    # Track position changes
    data['Position'] = data['Signal'].diff()
    position_column = f'Position_{short_period}_{long_period}'
    data[position_column] = data['Signal'].diff()

    signal_column = f'Signal_{short_period}_{long_period}'
    position_column = f'Position_{short_period}_{long_period}'
    data[signal_column] = np.where(data[f'SMA_{short_period}'] > data[f'SMA_{long_period}'], 1, 
                                   np.where(data[f'SMA_{short_period}'] < data[f'SMA_{long_period}'], -1, 0))
   
    data[position_column] = data[signal_column].diff()
    return data


def calculate_performance_metrics(data, position_column):
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data.")

    if position_column not in data.columns:
        raise KeyError(f"'{position_column}' column not found in data.")

    data['daily_return'] = data['close'].pct_change()
    data['strategy_return'] = data['daily_return'] * data[position_column].shift()

    total_return = data['strategy_return'].cumsum().iloc[-1]


    trades = data[position_column].diff().abs()
    wins = ((data['strategy_return'] > 0) & trades).sum()
    total_trades = trades.sum()
    win_rate = wins / total_trades if total_trades > 0 else 0

    gross_profit = data[data['strategy_return'] > 0]['strategy_return'].sum()
    gross_loss = -data[data['strategy_return'] < 0]['strategy_return'].sum()
    profit_factor = gross_profit / gross_loss if gross_loss > 0 else 0

    sharpe_ratio = data['strategy_return'].mean() / data['strategy_return'].std() * np.sqrt(252)

    negative_return = data[data['strategy_return'] < 0]['strategy_return']
    sortino_ratio = data['strategy_return'].mean() / negative_return.std() * np.sqrt(252)

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
    
    # Plotting the close prices
    plt.plot(data.index, data['close'], label='Price', alpha=0.7, color='lightblue')

    # Plotting SMA lines if applicable
    if short_period and long_period:
        plt.plot(data.index, data[f'SMA_{short_period}'], label=f'SMA {short_period}', alpha=0.7)
        plt.plot(data.index, data[f'SMA_{long_period}'], label=f'SMA {long_period}', alpha=0.7)

    # Plotting buy/sell signals
    plt.scatter(data.index[data['Position'] == 1], data['close'][data['Position'] == 1], label='Buy Signal', marker='^', color='green')
    plt.scatter(data.index[data['Position'] == -1], data['close'][data['Position'] == -1], label='Sell Signal', marker='v', color='red')

    # Adding metrics to the plot with conditional coloring
    vertical_position = 0.99
    for key, value in metrics.items():
        # Define favorable and unfavorable conditions
        color = 'green' if value > 0 else 'red'
        
        # Plot each metric with its color
        plt.text(0.01, vertical_position, f"{key}: {value:.2f}", transform=plt.gca().transAxes, verticalalignment='top', color=color, fontsize=14)
        vertical_position -= 0.05  # Adjust this value as needed to avoid overlap

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


def consensus_strategy(data, strategies, cooldown):
    """
    Implement a consensus strategy based on multiple SMA strategies.

    Parameters:
    data (DataFrame): DataFrame containing stock price data and strategy signals.
    strategies (list): List of dictionaries containing strategy details (signal columns).
    cooldown (int): Cooldown period after executing a trade.

    Returns:
    DataFrame: DataFrame with an additional column for the consensus strategy signals.
    """
    # Initialize consensus signal column
    data['Consensus_Signal'] = 0
    for strategy in strategies:
        signal_column = strategy['signal_column']
  
      # Aggregate signals from all strategies
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
            cooldown_counter = cooldown

    # Adjusting consensus positions to reflect valid trading actions
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
                # Apply SMA strategy and update 'data'
                sma_data = sma_strategy(data, short_period, long_period)
        
                signal_column = f'Signal_{short_period}_{long_period}'
                position_column = f'Position_{short_period}_{long_period}'
                
                metrics = calculate_performance_metrics(sma_data, position_column)

                # Plot and save the strategy results
                plot_filepath = plot_strategy(sma_data, symbol, f'SMA_{short_period}_{long_period}', metrics, short_period, long_period, plots_dir)
                print(f"Plot saved to {plot_filepath}")

                strategies.append({'signal_column': signal_column, 'position_column': position_column})

    # Apply and evaluate the consensus strategy
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
