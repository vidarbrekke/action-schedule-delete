import pandas as pd
import pandas_ta as ta

def add_indicators(data):
    """
    Adds various technical indicators to the DataFrame using Pandas TA.
    :param data: DataFrame with stock data (should contain 'open', 'high', 'low', 'close', 'volume' columns).
    :return: DataFrame with added indicators.
    """

    # Check if required columns exist
    required_columns = ['open', 'high', 'low', 'close', 'volume']
    if not all(column in data.columns for column in required_columns):
        raise ValueError('Data requires "open", "high", "low", "close", and "volume" columns.')

    # Simple Moving Average (SMA)

    # Add SMA_10 and SMA_50
    data['SMA_10'] = ta.sma(data['close'], length=10)
    data['SMA_20'] = ta.sma(data['close'], length=20)
    data['SMA_50'] = ta.sma(data['close'], length=50)
    data['SMA_100'] = ta.sma(data['close'], length=100)

    # Exponential Moving Average (EMA)
    data['EMA_10'] = ta.ema(data['close'], length=10)
    data['EMA_20'] = ta.ema(data['close'], length=20)
    data['EMA_50'] = ta.ema(data['close'], length=50)

    # Relative Strength Index (RSI)
    data['RSI_14'] = ta.rsi(data['close'], length=14)

    # Moving Average Convergence Divergence (MACD)
    macd = ta.macd(data['close'])
    data['MACD'] = macd['MACD_12_26_9']
    data['MACD_Hist'] = macd['MACDh_12_26_9']
    data['MACD_Signal'] = macd['MACDs_12_26_9']

    # Bollinger Bands
    bollinger = ta.bbands(data['close'], length=20, std=2)
    data['Bollinger_Upper'] = bollinger['BBU_20_2.0']
    data['Bollinger_Middle'] = bollinger['BBM_20_2.0']
    data['Bollinger_Lower'] = bollinger['BBL_20_2.0']

    # Average True Range (ATR)
    data['ATR_14'] = ta.atr(data['high'], data['low'], data['close'], length=14)

    # Stochastic Oscillator
    stoch = ta.stoch(data['high'], data['low'], data['close'])
    data['Stoch_K'] = stoch['STOCHk_14_3_3']
    data['Stoch_D'] = stoch['STOCHd_14_3_3']

    # Add more indicators as needed

    return data

# Example usage
if __name__ == "__main__":
    # Load data (assuming data is a DataFrame with required columns)
    data = pd.read_csv('path_to_your_data.csv')
    
    # Add indicators
    data_with_indicators = add_indicators(data)

    # Print the DataFrame with indicators
    print(data_with_indicators.head())
