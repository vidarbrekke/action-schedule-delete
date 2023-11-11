import pandas as pd
import numpy as np
import sys
import os

# Define the root directory of your project
root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# Define the analysis and data directories
analysis_dir = os.path.join(root_dir, 'analysis')
data_dir = os.path.join(root_dir, 'data')

# Append the 'analysis' and 'data' directory paths to sys.path
sys.path.append(analysis_dir)
sys.path.append(data_dir)

# Import after updating sys.path
try:
    from fetch_stock_data import fetch_data
    import indicators
except ModuleNotFoundError as e:
    print(f"Failed to import a module: {e}")
    print("Current sys.path:", sys.path)
    sys.exit(1)

def sma_strategy(data, short_period, long_period):
    # Add indicators to the data
    data_with_indicators = indicators.add_indicators(data)

    # Correctly handle the SettingWithCopyWarning
    data_with_indicators = data_with_indicators.copy()

    # Define buy/sell signals based on SMA crossover
    data_with_indicators.loc[long_period:, 'Signal'] = np.where(
        data_with_indicators.loc[long_period:, f'SMA_{short_period}'] > data_with_indicators.loc[long_period:, f'SMA_{long_period}'], 
        1, 0
    )
    data_with_indicators['Position'] = data_with_indicators['Signal'].diff()

    return data_with_indicators

def calculate_performance(data):
    # Use the correct column name 'close' (lowercase)
    if 'close' not in data.columns:
        raise KeyError("'close' column not found in data. Ensure the correct column name.")

    # Define performance metrics
    buy_signals = data[data['Position'] == 1]
    sell_signals = data[data['Position'] == -1]

    # Example: Calculate total return
    total_return = sell_signals['close'].sum() - buy_signals['close'].sum()

    return total_return

def backtest_sma(symbol, start_date, end_date, short_period, long_period):
    data = fetch_data(symbol, start_date, end_date)
    data_with_sma = sma_strategy(data, short_period, long_period)
    performance = calculate_performance(data_with_sma)

    return performance

if __name__ == '__main__':
    # Parameters
    symbol = 'AMD'
    start_date = '2022-04-01'  # Start date adjusted
    end_date = '2023-04-01'    # End date adjusted
    short_period = 10          # Define short period
    long_period = 50           # Define long period

    # Run backtest
    backtest_result = backtest_sma(symbol, start_date, end_date, short_period, long_period)
    print(backtest_result)
