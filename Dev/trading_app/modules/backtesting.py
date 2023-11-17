# backtesting.py

import argparse
import json
import os
import pandas as pd
from sqlalchemy.orm import sessionmaker
from database import engine, StockData
from sma_strategy import generate_sma_signals  # Adjust as needed

Session = sessionmaker(bind=engine)

def backtest_strategy(data, strategy_func, **strategy_params):
    # Filter out SMA-specific parameters
    sma_params = {k: v for k, v in strategy_params.items() if k in ['short_window', 'long_window']}
    data_with_signals = strategy_func(data, **sma_params)

    initial_balance = strategy_params.get('initial_balance', 1000)
    position = 0  # Initialize position
    balance = initial_balance
    stop_loss_percent = strategy_params.get('stop_loss_percent', 0.03)
    # ... other risk management parameters
    
    if data_with_signals.empty:
            print(f"No data available for backtesting {ticker}.")
            return {'final_balance': balance, 'total_return': 0.0}

    # Simulate trades with risk management
    for index, row in data_with_signals.iterrows():
        if row['signal'] == 1:  # Buy signal
            # Implement buying logic
            pass  # Replace 'pass' with actual logic

        elif row['signal'] == -1:  # Sell signal
            # Implement selling logic
            pass  # Replace 'pass' with actual logic

    # Calculate final balance and performance metrics
    final_balance = balance + (position * data_with_signals.iloc[-1]['close'])

    total_return = (final_balance - initial_balance) / initial_balance

    return {
        'final_balance': final_balance,
        'total_return': total_return
        # Include other metrics in this dictionary
    }



if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Run a backtesting simulation.")
    parser.add_argument('--config', type=str, required=True, help='Path to the test configuration JSON file')
    
    args = parser.parse_args()

    # Check if the config file exists
    if not os.path.exists(args.config):
        print(f"Config file not found: {args.config}")
        exit(1)

    # Load configuration from the JSON file
    with open(args.config, 'r') as file:
        config = json.load(file)

    session = Session()

    for ticker in config['tickers']:
        # Fetch data for the ticker from the database
        query = session.query(StockData).filter(StockData.ticker == ticker)
        data = pd.read_sql(query.statement, session.bind)

        # Run backtesting for each strategy
        for strategy_name, strategy_params in config['strategies'].items():
            print(f"Backtesting {strategy_name} on {ticker}")
            results = backtest_strategy(data, generate_sma_signals, **strategy_params)
            print(results)
