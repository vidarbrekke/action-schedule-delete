import pandas as pd

def calculate_sma(data, window):
    """
    Calculates Simple Moving Average (SMA) for a given window.
    Args:
    data (pd.DataFrame): DataFrame containing stock price data with a 'close' column.
    window (int): Number of periods to use for calculating the SMA.
    Returns:
    pd.Series: Series containing the SMA values.
    """
    if 'close' not in data.columns:
        raise ValueError("Data must contain 'close' column.")
    if window <= 0:
        raise ValueError("Window size must be a positive integer.")

    return data['close'].rolling(window=window, min_periods=1).mean()

def generate_sma_signals(data, short_window, long_window):
    """
    Generates buy and sell signals based on SMA crossovers.
    Args:
    data (pd.DataFrame): DataFrame containing stock price data.
    short_window (int): Window size for the short SMA.
    long_window (int): Window size for the long SMA.
    Returns:
    pd.DataFrame: DataFrame with SMA values and buy/sell signals.
    """
    if short_window <= 0 or long_window <= 0:
        raise ValueError("Window sizes must be positive integers.")
    if short_window >= long_window:
        raise ValueError("Short window must be smaller than long window.")

    data = data.copy()
    data['short_sma'] = calculate_sma(data, short_window)
    data['long_sma'] = calculate_sma(data, long_window)
    data['signal'] = 0
    data['signal'] = (data['short_sma'] > data['long_sma']).astype(int)

    return data

# Example usage:
# data = pd.read_csv('stock_data.csv')
# sma_signals = generate_sma_signals(data, short_window=20, long_window=50)
