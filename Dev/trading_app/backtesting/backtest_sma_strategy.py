import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import matplotlib.dates as mdates
import argparse
import os
from datetime import datetime
import sys

# Define directories
root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
analysis_dir = os.path.join(root_dir, 'analysis')
data_dir = os.path.join(root_dir, 'data')
plots_dir = os.path.join(root_dir, 'backtesting', 'plots')
sys.path.extend([analysis_dir, data_dir])

# Import custom modules
try:
    from fetch_stock_data import fetch_data
    import indicators
except ModuleNotFoundError as e:
    print(f"Failed to import a module: {e}")
    sys.exit(1)

def sma_strategy(data, short_period, long_period):
    # Add indicators
    data = indicators.add_indicators(data.copy())
    signal_column = f'Signal_{short_period}_{long_period}'
    position_column = f'Position_{short_period}_{long_period}'

    # Generate signals and calculate positions
    data[signal_column] = np.where(data[f'SMA_{short_period}'] > data[f'SMA_{long_period}'], 1, 0)
    data[position_column] = data[signal_column].diff()
    return data, signal_column, position_column

def calculate_performance(data, position_column):
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data.")
    profit = 0
    holding = False
    for _, row in data[data[position_column] != 0].iterrows():
        if row[position_column] == 1 and not holding:
            buy_price = row['close']
            holding = True
        elif row[position_column] == -1 and holding:
            profit += row['close'] - buy_price
            holding = False
    return profit

def plot_strategy(data, symbol, strategy_name, performance_score, raw_score, short_period, long_period, output_dir):
    plt.figure(figsize=(15, 8))
    date_column = data.columns[0]
    data[date_column] = pd.to_datetime(data[date_column])

    # Plot SMA lines
    if short_period and long_period:
        plt.plot(data[date_column], data[f'SMA_{short_period}'], label=f'SMA {short_period}', alpha=0.7)
        plt.plot(data[date_column], data[f'SMA_{long_period}'], label=f'SMA {long_period}', alpha=0.7)

    position_column = f'Position_{short_period}_{long_period}' if short_period and long_period else 'Consensus_Position'
    plt.scatter(data[date_column][data[position_column] == 1], data['close'][data[position_column] == 1], label='Buy Signal', marker='^', color='green')
    plt.scatter(data[date_column][data[position_column] == -1], data['close'][data[position_column] == -1], label='Sell Signal', marker='v', color='red')

    plt.title(f"{symbol} {strategy_name} Strategy (Performance: {performance_score}, Raw Score: {raw_score})")
    plt.xlabel('Date')
    plt.ylabel('Price')
    plt.legend()
    plt.grid(True)
    plt.gca().xaxis.set_major_formatter(mdates.DateFormatter('%m/%d/%y'))
    plt.gca().xaxis.set_major_locator(mdates.WeekdayLocator(interval=1))
    plt.xticks(rotation=45)

    filename = f"{symbol}_{strategy_name}_{pd.Timestamp.now().strftime('%Y%m%d%H%M%S')}.png"
    filepath = os.path.join(output_dir, filename)
    plt.savefig(filepath, dpi=300)
    plt.close()
    return filepath

def consensus_strategy(data, strategies, window_hours):
    window = f"{window_hours}H"
    rolling_signals = pd.DataFrame(index=data.index)

    for strategy in strategies:
        binary_signals = np.where(data[strategy['signal_column']] > 0, 1, 0)
        rolling_signals[strategy['signal_column']] = pd.Series(binary_signals, index=data.index).rolling(window).sum()

    data['Consensus_Signal'] = rolling_signals.sum(axis=1)
    consensus_threshold = len(strategies) // 2
    data['Consensus_Signal'] = np.where(abs(data['Consensus_Signal']) > consensus_threshold, np.sign(data['Consensus_Signal']), 0)
    data['Consensus_Position'] = data['Consensus_Signal'].diff()
    return data

def backtest_sma(symbol, start_date, end_date, short_periods, long_periods, window_hours):
    data = fetch_data(symbol, start_date, end_date)
    strategies = []

    for short_period in short_periods:
        for long_period in long_periods:
            if short_period < long_period:
                sma_data, signal_column, position_column = sma_strategy(data, short_period, long_period)
                data[signal_column] = sma_data[signal_column]  # Update main data with new signal
                data[position_column] = sma_data[position_column]  # Update main data with new position

                performance = calculate_performance(sma_data, position_column)
                raw_score = calculate_raw_score(sma_data, position_column)
                plot_path = plot_strategy(sma_data, symbol, f"SMA_{short_period}_{long_period}", performance, raw_score, short_period, long_period, plots_dir)
                print(f"Result for {symbol} ({short_period}, {long_period}): Performance={performance}, Raw Score={raw_score}, Plot={plot_path}")
                
                strategies.append({'short_period': short_period, 'long_period': long_period, 'signal_column': signal_column})

    data_with_consensus = consensus_strategy(data, strategies, window_hours)
    consensus_performance = calculate_performance(data_with_consensus, 'Consensus_Position')
    consensus_raw_score = calculate_raw_score(data_with_consensus, 'Consensus_Position')
    consensus_plot_path = plot_strategy(data_with_consensus, symbol, 'Consensus_Strategy', consensus_performance, consensus_raw_score, None, None, plots_dir)
    print(f"Consensus Result for {symbol}: Performance={consensus_performance}, Raw Score={consensus_raw_score}, Plot={consensus_plot_path}")

def calculate_raw_score(data, position_column):
    buy_signals = data[data[position_column] == 1]
    sell_signals = data[data[position_column] == -1]
    total_return = sell_signals['close'].sum() - buy_signals['close'].sum()
    return total_return

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Backtest SMA Strategy')
    parser.add_argument('symbol', type=str, help='Stock symbol to backtest')
    args = parser.parse_args()
    symbol = args.symbol
    start_date, end_date = '2022-04-01', '2023-04-01'
    short_periods, long_periods = [10, 20], [50, 100]
    window_hours = 48

    backtest_sma(symbol, start_date, end_date, short_periods, long_periods, window_hours)
