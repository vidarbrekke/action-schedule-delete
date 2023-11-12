import pandas as pd
import numpy as np
import matplotlib.pyplot as plt
import matplotlib.dates as mdates
import argparse
import os
import sys
from datetime import datetime

# Define root, analysis, data, and plots directories
root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
analysis_dir = os.path.join(root_dir, 'analysis')
data_dir = os.path.join(root_dir, 'data')
plots_dir = os.path.join(root_dir, 'backtesting', 'plots')
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
    signal_column = f'Signal_{short_period}_{long_period}'
    data_with_indicators[signal_column] = np.where(
        data_with_indicators[f'SMA_{short_period}'] > data_with_indicators[f'SMA_{long_period}'], 1,
        np.where(data_with_indicators[f'SMA_{short_period}'] < data_with_indicators[f'SMA_{long_period}'], -1, 0))
    position_column = f'Position_{short_period}_{long_period}'
    data_with_indicators[position_column] = data_with_indicators[signal_column].diff()
    return data_with_indicators, signal_column, position_column



# Performance Calculation Function
def calculate_performance(data, position_column):
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data.")
    positions = data[data[position_column] != 0]
    profit = 0
    holding = False
    for index, row in positions.iterrows():
        if row[position_column] == 1 and not holding:
            buy_price = row['close']
            holding = True
        elif row[position_column] == -1 and holding:
            sell_price = row['close']
            profit += (sell_price - buy_price)
            holding = False
    return profit



# Plotting Function
def plot_strategy(data, symbol, strategy_name, performance_score, raw_score, short_period, long_period, output_dir):
    plt.figure(figsize=(15, 8))

    date_column = data.columns[0]
    data[date_column] = pd.to_datetime(data[date_column])

    # Plot SMA lines if applicable
    if short_period and long_period:
        plt.plot(data[date_column], data[f'SMA_{short_period}'], label=f'SMA {short_period}', alpha=0.7)
        plt.plot(data[date_column], data[f'SMA_{long_period}'], label=f'SMA {long_period}', alpha=0.7)

    position_column = f'Position_{short_period}_{long_period}' if short_period and long_period else 'Consensus_Position'
    plt.scatter(data[date_column][data[position_column] == 1], data['close'][data[position_column] == 1], label='Buy Signal', marker='^', color='green')
    plt.scatter(data[date_column][data[position_column] == -1], data['close'][data[position_column] == -1], label='Sell Signal', marker='v', color='red')

    plt.title(f"{symbol} {strategy_name} Strategy (Buy/Sell All Score: {performance_score}, Raw Score: {raw_score})")
    plt.xlabel('Date')
    plt.ylabel('Price')

    # Formatting the x-axis
    plt.gca().xaxis.set_major_formatter(mdates.DateFormatter('%m/%d/%y'))
    plt.gca().xaxis.set_major_locator(mdates.WeekdayLocator(interval=1))  # Markers every 7 days

    plt.xticks(rotation=45, fontsize='small')
    plt.legend()
    plt.grid(True)

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

    consensus_threshold = len(strategies) // 2
    data['Consensus_Signal'] = np.where(consensus_signal > consensus_threshold, 1, 0)

    # Eliminate consecutive duplicate signals
    data['Consensus_Signal'] = data['Consensus_Signal'].where(data['Consensus_Signal'].shift() != data['Consensus_Signal'], 0)

    data['Consensus_Position'] = data['Consensus_Signal'].diff()
    return data



def calculate_raw_score(data, position_column):
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data.")
    buy_signals = data[data[position_column] == 1]
    sell_signals = data[data[position_column] == -1]
    total_return = sell_signals['close'].sum() - buy_signals['close'].sum()
    return total_return



# Backtesting Function for SMA
def backtest_sma(symbol, start_date, end_date, short_period, long_period):
    data = fetch_data(symbol, start_date, end_date)
    data_with_sma, signal_column, position_column = sma_strategy(data, short_period, long_period)
    performance = calculate_performance(data_with_sma, position_column)
    plot_path = plot_strategy(data_with_sma, symbol, f"SMA_{short_period}_{long_period}", performance, short_period, long_period, plots_dir)
    return performance, plot_path

# Main Execution
if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='Backtest SMA Strategy')
    parser.add_argument('symbol', type=str, help='Stock symbol to backtest')
    args = parser.parse_args()
    symbol = args.symbol
    start_date = '2022-04-01'
    end_date = '2023-04-01'
    
    # Fetch data once and use it for all strategies
    data = fetch_data(symbol, start_date, end_date)

    # Define periods for short and long SMAs
    short_periods = [10, 20]
    long_periods = [50, 100]

    strategies = []
    for short_period in short_periods:
        for long_period in long_periods:
            if short_period < long_period:
                data_with_sma, signal_column, position_column = sma_strategy(data, short_period, long_period)
                
                # Calculate performance based on the SMA strategy
                performance = calculate_performance(data_with_sma, position_column)
                
                # Calculate raw score
                raw_score = calculate_raw_score(data_with_sma, position_column)

                # Plotting and other operations that use 'performance'
                plot_path = plot_strategy(data_with_sma, symbol, f"SMA_{short_period}_{long_period}", performance, raw_score, short_period, long_period, plots_dir)
                print(f"Backtest Result for {symbol} ({short_period}, {long_period}): {performance}, Raw Score: {raw_score}")
                print(f"Plot saved as: {plot_path}")

    # Execute consensus strategy
    data_with_consensus = consensus_strategy(data, strategies)
    consensus_performance = calculate_performance(data_with_consensus, 'Consensus_Position')

    # Calculate raw score for consensus (if applicable)
    consensus_raw_score = calculate_raw_score(data_with_consensus, 'Consensus_Position')

    # Plotting consensus strategy
    consensus_plot_path = plot_strategy(data_with_consensus, symbol, 'Consensus_Strategy', consensus_performance, consensus_raw_score, None, None, plots_dir)
    print(f"Consensus Strategy Result for {symbol}: {consensus_performance}")
    print(f"Consensus Plot saved as: {consensus_plot_path}")
