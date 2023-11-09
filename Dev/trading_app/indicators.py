import pandas as pd
import numpy as np

class SimpleMovingAverage:
    def __init__(self, period):
        self.period = period

    def calculate_sma(self, data):
        if 'CLOSE' not in data.columns:
            raise ValueError('Data requires a "CLOSE" column for SMA calculation.')
        return data['CLOSE'].rolling(window=self.period).mean()

class ExponentialMovingAverage:
    def __init__(self, period):
        self.period = period

    def calculate_ema(self, data):
        if 'CLOSE' not in data.columns:
            raise ValueError('Data requires a "CLOSE" column for EMA calculation.')
        return data['CLOSE'].ewm(span=self.period, adjust=False).mean()

class RelativeStrengthIndex:
    def __init__(self, period):
        self.period = period

    def calculate_rsi(self, data):
        if 'CLOSE' not in data.columns:
            raise ValueError('Data requires a "CLOSE" column for RSI calculation.')
        delta = data['CLOSE'].diff()
        gain = delta.clip(lower=0).rolling(window=self.period).mean()
        loss = -delta.clip(upper=0).rolling(window=self.period).mean()
        rs = gain / (loss + 1e-10)
        rsi = 100 - (100 / (1 + rs))
        return rsi

class MovingAverageConvergenceDivergence:
    def __init__(self, long_period=26, short_period=12, signal_period=9):
        self.long_period = long_period
        self.short_period = short_period
        self.signal_period = signal_period

    def calculate_macd(self, data):
        if 'CLOSE' not in data.columns:
            raise ValueError('Data requires a "CLOSE" column for MACD calculation.')
        ema_long = data['CLOSE'].ewm(span=self.long_period, adjust=False).mean()
        ema_short = data['CLOSE'].ewm(span=self.short_period, adjust=False).mean()
        macd_line = ema_short - ema_long
        signal_line = macd_line.ewm(span=self.signal_period, adjust=False).mean()
        return macd_line, signal_line

class BollingerBands:
    def __init__(self, period=20, std_dev_multiplier=2):
        self.period = period
        self.std_dev_multiplier = std_dev_multiplier

    def calculate_bollinger_bands(self, data):
        if 'CLOSE' not in data.columns:
            raise ValueError('Data requires a "CLOSE" column for Bollinger Bands calculation.')
        sma = data['CLOSE'].rolling(window=self.period).mean()
        std_dev = data['CLOSE'].rolling(window=self.period).std()
        upper_band = sma + (std_dev * self.std_dev_multiplier)
        lower_band = sma - (std_dev * self.std_dev_multiplier)
        return sma, upper_band, lower_band

# Additional classes would go here with similar checks and error handling
# Ensure the DataFrame operations are vectorized and avoid using loops where possible
