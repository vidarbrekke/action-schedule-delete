# backtesting.py
# useage: python modules/backtesting.py --config config.json --days 180


import argparse
import json
import os
import pandas as pd
from sqlalchemy.orm import sessionmaker
from database import engine, StockData
from sma_strategy import generate_sma_signals
from data_acquisition import fetch_and_update_stock_data

Session = sessionmaker(bind=engine)

def calculate_sma(data, window):
    return data['close'].rolling(window=window, min_periods=1).mean()

def generate_sma_signals(data, short_window, long_window):
    data = data.copy()
    data['short_sma'] = calculate_sma(data, short_window)
    data['long_sma'] = calculate_sma(data, long_window)
    data['signal'] = 0
    data['signal'] = (data['short_sma'] > data['long_sma']).astype(int)
    return data

def backtest_strategy(data, ticker, strategy_func, strategy_params):
    results = []
    for strategy_name, params in strategy_params.items():
        short_window = params.get('short_window')
        long_window = params.get('long_window')
        initial_balance = params.get('initial_balance', 1000)
        stop_loss_percent = params.get('stop_loss_percent', 0.03)

        if short_window is not None and long_window is not None:
            data_with_signals = strategy_func(data, short_window, long_window)
            if data_with_signals.empty:
                continue

            position = 0
            balance = initial_balance
            trade_price = 0

            for index, row in data_with_signals.iterrows():
                if row['signal'] == 1 and position == 0:
                    position = balance // row['close']
                    balance -= position * row['close']
                    trade_price = row['close']

                elif row['signal'] == 0 and position > 0:
                    balance += position * row['close']
                    position = 0
                    trade_price = 0

            final_balance = balance + (position * trade_price)
            total_return = (final_balance - initial_balance) / initial_balance
            results.append({
                'strategy': strategy_name,
                'final_balance': final_balance,
                'total_return': total_return
            })
        else:
            print(f"Missing window parameters for strategy {strategy_name}")
    
    return results

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Run a backtesting simulation.")
    parser.add_argument('--config', type=str, required=True, help='Path to the test configuration JSON file')
    parser.add_argument('--days', type=int, default=180, help='Number of days to include in backtesting')

    args = parser.parse_args()


    if not os.path.exists(args.config):
        print(f"Config file not found: {args.config}")
        exit(1)

    with open(args.config, 'r') as file:
        config = json.load(file)

    session = Session()

    for ticker in config['tickers']:
        last_record = session.query(StockData).filter(StockData.ticker == ticker).order_by(StockData.date.desc()).first()
        
        if not last_record:
            print(f"No data found for {ticker}. Fetching maximum available data.")
            fetch_and_update_stock_data(ticker)
        else:
            fetch_and_update_stock_data(ticker, last_record.date.strftime('%Y-%m-%d'))

        query = session.query(StockData).filter(StockData.ticker == ticker)
        data = pd.read_sql(query.statement, session.bind)

        # Filter data to the last 'args.days' days
        end_date = data['date'].max()
        start_date = end_date - pd.Timedelta(days=args.days)
        filtered_data = data[data['date'] >= start_date]

        # Check if the actual data range is smaller than requested
        actual_days = (end_date - filtered_data['date'].min()).days
        if actual_days < args.days:
            print(f"The number of days ({args.days}) is larger than the available data for {ticker} "
                  f"({actual_days} days). Using all available data instead.")

        results = backtest_strategy(filtered_data, ticker, generate_sma_signals, config['strategies'])
        print(f"Backtesting results for {ticker}:", results)