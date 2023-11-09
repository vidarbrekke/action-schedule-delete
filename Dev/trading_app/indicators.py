import pandas as pd
import numpy as np


class SimpleMovingAverage:
    def __init__(self, period):
        self.period = period

    def calculate_sma(self, data):
        return data['CLOSE'].rolling(window=self.period).mean()

class ExponentialMovingAverage:
    def __init__(self, period):
        self.period = period

    def calculate_ema(self, data):
        return data['CLOSE'].ewm(span=self.period, adjust=False).mean()

class RelativeStrengthIndex:
    def __init__(self, period):
        self.period = period

    def calculate_rsi(self, data):
        delta = data['CLOSE'].diff()
        gain = (delta.where(delta > 0, 0)).rolling(window=self.period).mean()
        loss = (-delta.where(delta < 0, 0)).rolling(window=self.period).mean()

        # Avoid division by zero
        rs = gain / (loss + 1e-10)
        rsi = 100 - (100 / (1 + rs))
        return rsi


class MovingAverageConvergenceDivergence:
    def __init__(self, long_period=26, short_period=12, signal_period=9):
        self.long_period = long_period
        self.short_period = short_period
        self.signal_period = signal_period

    def calculate_macd(self, data):
        """
        Calculate the Moving Average Convergence Divergence (MACD) for the given data.

        :param data: A pandas DataFrame with a 'CLOSE' column.
        :return: A pandas DataFrame with the MACD line and Signal line.
        """
        ema_long = data['CLOSE'].ewm(span=self.long_period, adjust=False).mean()
        ema_short = data['CLOSE'].ewm(span=self.short_period, adjust=False).mean()
        data['MACD'] = ema_short - ema_long
        data['MACD_Signal'] = data['MACD'].ewm(span=self.signal_period, adjust=False).mean()
        return data

class BollingerBands:
    def __init__(self, period=20, std_dev_multiplier=2):
        self.period = period
        self.std_dev_multiplier = std_dev_multiplier

    def calculate_bollinger_bands(self, data):
        """
        Calculate Bollinger Bands for the given data.

        :param data: A pandas DataFrame with a 'CLOSE' column.
        :return: A pandas DataFrame with Bollinger Bands columns added.
        """
        data['Middle_Band'] = data['CLOSE'].rolling(window=self.period).mean()
        data['Std_Dev'] = data['CLOSE'].rolling(window=self.period).std()
        data['Upper_Band'] = data['Middle_Band'] + (data['Std_Dev'] * self.std_dev_multiplier)
        data['Lower_Band'] = data['Middle_Band'] - (data['Std_Dev'] * self.std_dev_multiplier)
        return data

class VolumeWeightedAveragePrice:
    def __init__(self):
        pass

    def calculate_vwap(self, data):
        """
        Calculate VWAP for the given intraday data.
        :param data: A pandas DataFrame with 'High', 'Low', 'Close', and 'Volume' columns.
        :return: A pandas DataFrame with the 'VWAP' column added.
        """
        typical_price = (data['High'] + data['Low'] + data['Close']) / 3
        vwap = (typical_price * data['Volume']).cumsum() / data['Volume'].cumsum()
        data['VWAP'] = vwap
        return data

class StochasticOscillator:
    def __init__(self, k_period=14, d_period=3):
        self.k_period = k_period
        self.d_period = d_period

    def calculate_stochastic_oscillator(self, data):
        """
        Calculate Stochastic Oscillator for the given data.
        :param data: A pandas DataFrame with 'High', 'Low', and 'Close' columns.
        :return: A pandas DataFrame with '%K' and '%D' columns added.
        """
        low_min  = data['Low'].rolling(window=self.k_period).min()
        high_max = data['High'].rolling(window=self.k_period).max()

        data['%K'] = ((data['Close'] - low_min) / (high_max - low_min)) * 100
        data['%D'] = data['%K'].rolling(window=self.d_period).mean()
        return data

# Add more indicator classes as needed
