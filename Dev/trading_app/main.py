from analysis.indicators import (
    SimpleMovingAverage,
    ExponentialMovingAverage,
    RelativeStrengthIndex,
    MovingAverageConvergenceDivergence,
    BollingerBands
)
from database import get_stock_data_from_db

def main():
    # Define your periods for the indicators
    sma_period = 20
    ema_period = 20
    rsi_period = 14
    macd_long_period = 26
    macd_short_period = 12
    macd_signal_period = 9
    bb_period = 20
    bb_std_dev_multiplier = 2

    # Assume you've already selected a ticker, for example 'AMD'
    ticker = 'AMD'

    # Get stock data from your database
    data = get_stock_data_from_db(ticker)

    # Calculating SMA
    sma_calculator = SimpleMovingAverage(sma_period)
    data[f'SMA_{sma_period}'] = sma_calculator.calculate_sma(data)

    # Calculating EMA
    ema_calculator = ExponentialMovingAverage(ema_period)
    data[f'EMA_{ema_period}'] = ema_calculator.calculate_ema(data)

    # Calculating RSI
    rsi_calculator = RelativeStrengthIndex(rsi_period)
    data[f'RSI_{rsi_period}'] = rsi_calculator.calculate_rsi(data)
   
    # Calculating MACD
    macd_calculator = MovingAverageConvergenceDivergence(macd_long_period, macd_short_period, macd_signal_period)
    macd_line, signal_line = macd_calculator.calculate_macd(data)
    data['MACD'] = macd_line
    data['MACD_Signal'] = signal_line

    # Calculating Bollinger Bands
    bb_calculator = BollingerBands(bb_period, bb_std_dev_multiplier)
    middle_band, upper_band, lower_band = bb_calculator.calculate_bollinger_bands(data)
    data['Middle_Band'] = middle_band
    data['Upper_Band'] = upper_band
    data['Lower_Band'] = lower_band

    # Print out the full DataFrame to verify the calculations
    print(data)

if __name__ == "__main__":
    main()
