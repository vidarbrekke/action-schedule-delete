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
    # Reset index to ensure it starts from 0 and avoid KeyError
    data = data.reset_index(drop=True)
    
    data['short_sma'] = calculate_sma(data, short_window)
    data['long_sma'] = calculate_sma(data, long_window)
    data['signal'] = 0

    for i in range(1, len(data)):
        if data['short_sma'][i] > data['long_sma'][i] and data['short_sma'][i-1] <= data['long_sma'][i-1]:
            data.at[i, 'signal'] = 1  # Buy
        elif data['short_sma'][i] < data['long_sma'][i] and data['short_sma'][i-1] > data['long_sma'][i-1]:
            data.at[i, 'signal'] = -1  # Sell

    return data
