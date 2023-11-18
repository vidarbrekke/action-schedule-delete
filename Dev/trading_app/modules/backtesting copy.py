# backtesting.py
# useage: python modules/backtesting.py --config config.json --days 180

import argparse
import json
import os
import pandas as pd
import matplotlib.pyplot as plt
import matplotlib.ticker as mticker
import numpy as np
from sqlalchemy.orm import sessionmaker
from database import engine, StockData
from data_acquisition import fetch_and_update_stock_data
import datetime
from sma_strategy import generate_sma_signals

Session = sessionmaker(bind=engine)

def plot_strategy_performance(data, strategy_name, strategy_params, metrics, ticker, timestamp):
    # Ensure the plots directory exists
    plots_dir = "plots"
    os.makedirs(plots_dir, exist_ok=True)

    fig, ax1 = plt.subplots(figsize=(10, 6), dpi=400)

    # Plotting stock prices and SMA lines
    ax1.plot(data['date'], data['close'], label='Close Price', color='blue', alpha=0.6)
    ax1.plot(data['date'], data['short_sma'], label=f'Short SMA ({strategy_params["short_window"]})', color='orange', alpha=0.6)
    ax1.plot(data['date'], data['long_sma'], label=f'Long SMA ({strategy_params["long_window"]})', color='purple', alpha=0.6)

    # Plotting signals on the top layer with no transparency
    ax1.scatter(data[data['signal'] == 1]['date'], data[data['signal'] == 1]['close'], label='Buy Signal', marker='^', color='green')
    ax1.scatter(data[data['signal'] == -1]['date'], data[data['signal'] == -1]['close'], label='Sell Signal', marker='v', color='red')

    # Other plot settings
    ax1.set_title(f'{ticker} - {strategy_name} Strategy Performance')
    ax1.set_xlabel('Date')
    ax1.set_ylabel('Price')
    ax1.legend(loc='upper left')
    ax1.xaxis.set_major_locator(mticker.MaxNLocator(10))
    ax1.grid(True)

    # Displaying strategy parameters and metrics in separate columns
    fig.subplots_adjust(bottom=0.25)
    params_text = "\n".join([f"{key}: {value}" for key, value in strategy_params.items()])
    ax1.text(0.05, -0.25, f"Strategy Parameters:\n{params_text}", verticalalignment='top', horizontalalignment='left', fontsize=8, transform=ax1.transAxes)

    # Displaying metrics with color coding
    initial_balance = strategy_params.get('initial_balance', 1000)
    y_offset = -0.25
    ax1.text(0.5, y_offset, "Metrics:", verticalalignment='top', horizontalalignment='left', fontsize=8, transform=ax1.transAxes)
    for key, value in metrics.items():
        y_offset -= 0.05
        display_value = f"{value:.2f}" if isinstance(value, (int, float)) else value
        color = 'green' if (key == 'final_balance' and value > initial_balance) or (key != 'final_balance' and isinstance(value, (int, float)) and value > 0) else 'black'
        ax1.text(0.5, y_offset, f"{key}: {display_value}", verticalalignment='top', horizontalalignment='left', fontsize=8, color=color, transform=ax1.transAxes)

    plt.tight_layout()
    plt.savefig(os.path.join(plots_dir, f"{ticker}_{strategy_name}_performance_{timestamp}.png"))
    plt.close()



def save_performance_report(all_reports, reports_dir, ticker):
    # Ensure the reports directory exists
    os.makedirs(reports_dir, exist_ok=True)

    # Generate timestamp for file naming
    timestamp = datetime.datetime.now().strftime("%m-%d-%H-%M-%S")

    # Define the filenames
    json_filename = os.path.join(reports_dir, f"performance_report_{ticker}_{timestamp}.json")
    txt_filename = os.path.join(reports_dir, f"performance_report_{ticker}_{timestamp}.txt")

    # Save the reports to a JSON file
    with open(json_filename, 'w') as json_file:
        json.dump(all_reports, json_file, indent=4)

    # Save the reports to a text file
    with open(txt_filename, 'w') as txt_file:
        for report in all_reports:
            txt_file.write(f"Strategy Name: {report['strategy_name']}\n")
            txt_file.write(f"Ticker: {report['ticker']}\n")
            txt_file.write("Metrics:\n")
            for key, value in report['metrics'].items():
                txt_file.write(f"{key}: {value}\n")
            txt_file.write("Strategy Parameters:\n")
            for key, value in report['strategy_params'].items():
                txt_file.write(f"{key}: {value}\n")
            txt_file.write("\n\n")


def backtest_strategy(data, ticker, strategy_func, strategy_params):
    # Initialize a list to hold all strategy reports
    all_reports = []

    # Generate timestamp at the beginning of the function
    timestamp = datetime.datetime.now().strftime("%m-%d-%M-%S")

    # Iterate through each strategy
    for strategy_name, params in strategy_params.items():
        print(f"Running {strategy_name} on {ticker}")

        # Extract short_window and long_window from strategy parameters
        short_window = params.get('short_window')
        long_window = params.get('long_window')
        initial_balance = params.get('initial_balance', 1000)
        signal_strength = params.get('signal_strength', 1)  # Default to full strength

        if short_window and long_window:
            data_with_signals = strategy_func(data, short_window, long_window)

            # Counting buy and sell signals
            buy_signals = sum(data_with_signals['signal'] == 1)
            sell_signals = sum(data_with_signals['signal'] == -1)
            print(f"Strategy: {strategy_name}, Buy signals: {buy_signals}, Sell signals: {sell_signals}")

            # Initialize trading variables
            position = 0
            balance = initial_balance
            trade_results = []

            # Iterate over each row in data_with_signals
            for index, row in data_with_signals.iterrows():
                # Buy/Sell Logic
                if row['signal'] == 1 and position == 0:  # Buy signal
                    shares_to_buy = (balance // row['close']) * signal_strength
                    position += shares_to_buy
                    balance -= shares_to_buy * row['close']
                    trade_result = -shares_to_buy * row['close']  # Negative because it's a cost
                    trade_results.append(trade_result)

                elif row['signal'] == -1 and position > 0:  # Sell signal
                    shares_to_sell = position * signal_strength
                    balance += shares_to_sell * row['close']
                    position -= shares_to_sell
                    trade_result = shares_to_sell * row['close']  # Positive because it's a gain
                    trade_results.append(trade_result)

            # Final calculations for the strategy
            final_balance = balance + (position * data_with_signals.iloc[-1]['close'])
            total_return = (final_balance - initial_balance) / initial_balance
            total_trades = len(trade_results)
            winning_trades = len([result for result in trade_results if result > 0])
            losing_trades = total_trades - winning_trades
            win_rate = winning_trades / total_trades if total_trades > 0 else 0
            total_winning = sum([result for result in trade_results if result > 0])
            total_losing = -sum([result for result in trade_results if result < 0])
            profit_factor = total_winning / total_losing if total_losing > 0 else np.inf
            expectancy = sum(trade_results) / total_trades if total_trades > 0 else 0

            metrics = {
                'strategy': strategy_name,
                'final_balance': final_balance,
                'total_return': total_return,
                'win_rate': win_rate,
                'profit_factor': profit_factor,
                'expectancy': expectancy
                # Additional metrics can be added here
            }

            

 # Collect metrics and parameters for the report
        report = {
            "strategy_name": strategy_name,
            "ticker": ticker,
            "metrics": metrics, 
            "strategy_params": params
        }

        # Append the report to the list
        all_reports.append(report)

        # Plotting and saving plots
        plot_strategy_performance(data_with_signals, strategy_name, params, metrics, ticker, timestamp)

    # Save all reports once all strategies are processed
    reports_dir = "reports"  # Define the directory for reports
    save_performance_report(all_reports, reports_dir, ticker)

    return all_reports



# main
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