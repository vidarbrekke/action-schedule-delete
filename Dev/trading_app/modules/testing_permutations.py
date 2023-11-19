# testing_permutations.py

import itertools
import argparse
import json
import pandas as pd
from sqlalchemy.orm import sessionmaker
from database import engine, StockData
from backtesting import backtest_strategy
from sma_strategy import generate_sma_signals
from common import identify_market_regime_enhanced, adjust_risk_parameters


Session = sessionmaker(bind=engine)


def run_parameter_permutations(ticker, short_window_options, long_window_options, risk_per_trade_percent_options, risk_reward_ratio_options):
    results_dict = {}

    session = Session()
    query = session.query(StockData).filter(StockData.ticker == ticker)
    data = pd.read_sql(query.statement, session.bind)
    session.close()

    data_with_regime = identify_market_regime_enhanced(data)

    for short_window, long_window, risk_per_trade_percent, risk_reward_ratio in itertools.product(
        short_window_options, long_window_options, risk_per_trade_percent_options, risk_reward_ratio_options):

        param_combination = f"SW_{short_window}_LW_{long_window}_Risk_{risk_per_trade_percent}_RR_{risk_reward_ratio}"
        strategy_params = {
            "strategy_name": "SMA_Strategy",
            "short_window": short_window,
            "long_window": long_window,
            "risk_per_trade_percent": risk_per_trade_percent,
            "risk_reward_ratio": risk_reward_ratio,
            "stop_loss_percent": 0.02, 
            "take_profit_percent": 0.04,
            "max_drawdown_percent": 0.15,
            "cooldown_period_minutes": 30,
            "first_trade_limit_percent": 0.25
        }

        latest_regime = data_with_regime['regime'].iloc[-1]
        adjusted_params = adjust_risk_parameters(strategy_params, latest_regime)

        try:
            results = backtest_strategy(data, ticker, generate_sma_signals, False, adjusted_params)
            results_dict[param_combination] = results
        except Exception as e:
            print(f"Error in running permutation {param_combination}: {e}")

    return results_dict

def main():
    short_window_options = [5, 15, 20]
    long_window_options = [40, 50]
    risk_per_trade_percent_options = [0.5, 1, 1.5]
    risk_reward_ratio_options = [2, 3]

    parser = argparse.ArgumentParser(description="Run parameter permutation tests for a specific ticker.")
    parser.add_argument('--ticker', type=str, required=True, help='Ticker symbol to run the tests on')
    args = parser.parse_args()
    ticker = args.ticker

    results = run_parameter_permutations(ticker, short_window_options, long_window_options, risk_per_trade_percent_options, risk_reward_ratio_options)

    with open(f"{ticker}_parameter_test_results.json", "w") as file:
        json.dump(results, file, indent=4)

    print(f"Parameter testing completed for {ticker}. Results saved to {ticker}_parameter_test_results.json")

if __name__ == "__main__":
    main()
