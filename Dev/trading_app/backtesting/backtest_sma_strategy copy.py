import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import matplotlib.dates as mdates
import argparse
import os
import sys
from datetime import datetime

# Define the root directory of your project
root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
analysis_dir = os.path.join(root_dir, 'analysis')
data_dir = os.path.join(root_dir, 'data')
plots_dir = os.path.join(root_dir, 'backtesting', 'plots')

# Append the 'analysis' and 'data' directory paths to sys.path
sys.path.append(analysis_dir)
sys.path.append(data_dir)

# Import modules
try:
    from fetch_stock_data import fetch_data
    import indicators
except ModuleNotFoundError as e:
    print(f"Failed to import a module: {e}")
    sys.exit(1)

# SMA Strategy Function
def sma_strategy(data, short_period, long_period):
    data_with_indicators = indicators.add_indicators(data)
    data_with_indicators = data_with_indicators.copy()
    signal_column = f'Signal_{short_period}_{long_period}'
    position_column = f'Position_{short_period}_{long_period}'

    data_with_indicators[signal_column] = np.where(
        data_with_indicators[f'SMA_{short_period}'] > data_with_indicators[f'SMA_{long_period}'], 1, 0
    )
    data_with_indicators[position_column] = data_with_indicators[signal_column].diff()

    return data_with_indicators, signal_column, position_column

# Performance Calculation Function
def calculate_performance(data, position_column):
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data.")
    buy_signals = data[data[position_column] == 1]
    sell_signals = data[data[position_column] == -1]
    total_return = sell_signals['close'].sum() - buy_signals['close'].sum()
    return total_return

# Plotting Function
def plot_strategy(data, symbol, strategy_name, performance_score, short_period, long_period, output_dir):
    plt.figure(figsize=(15, 8))
    plt.plot(data.index, data['close'], label='Close Price', color='#ADD8E6')

    if short_period is not None and long_period is not None:
        plt.plot(data.index, data[f'SMA_{short_period}'], label=f'SMA {short_period}', alpha=0.7)
        plt.plot(data.index, data[f'SMA_{long_period}'], label=f'SMA {long_period}', alpha=0.7)
        position_column = f'Position_{short_period}_{long_period}'
    else:
        position_column = 'Consensus_Position'

    plt.scatter(data.index[data[position_column] == 1], data['close'][data[position_column] == 1], label='Buy Signal', marker='^', color='green')
    plt.scatter(data.index[data[position_column] == -1], data['close'][data[position_column] == -1], label='Sell Signal', marker='v', color='red')

    plt.title(f"{symbol} {strategy_name} Strategy (Score: {performance_score})")
    plt.xlabel('Date')
    plt.ylabel('Price')
    plt.legend()
    plt.grid(True)
    plt.gca().xaxis.set_major_formatter(mdates.DateFormatter('%m/%d/%y'))
    plt.gca().xaxis.set_major_locator(mdates.DayLocator(interval=30))
    plt.xticks(rotation=45, fontsize='small')
    filename = f"{symbol}_{strategy_name}_{pd.Timestamp.now().strftime('%Y%m%d%H%M%S')}.png"
    filepath = os.path.join(output_dir, filename)
    plt.savefig(filepath, dpi=300)
    plt.close()
    return filepath

# Consensus Strategy Function
def consensus_strategy(data, strategies):
    consensus_signal = np.zeros(len(data))
    for strategy in strategies:
        consensus_signal += (data[strategy['signal_column']] > 0).astype(int)

    consensus_threshold = len(strategies) / 2
    data['Consensus_Signal'] = np.where(consensus_signal >= consensus_threshold, 1, 0)
    data['Consensus_Position'] = data['Consensus_Signal'].diff()
    return data

# Backtesting Function for SMA
def backtest_sma(symbol, start_date, end_date, short_period, long_period):
    data = fetch_data(symbol, start_date, end_date)
    data_with_sma, signal_column, position_column = sma_strategy(data, short_period, long_period)
    performance = calculate_performance(data_with_sma, position_column)
    plot_path = plot_strategy(data_with_sma, symbol, f"SMA_{short_period}_{long_period}",
