# verify_stock_data.py
import sqlite3
import os
import matplotlib.pyplot as plt
import pandas as pd
import sys

def check_ticker_data(ticker):
    script_dir = os.path.dirname(os.path.abspath(__file__))
    database_path = os.path.join(script_dir, "trading_app.db")

    # Connect to the SQLite database
    conn = sqlite3.connect(database_path)
    cursor = conn.cursor()

    # Check if the ticker exists in the 'stocks' table and get its ID
    cursor.execute("SELECT id FROM stocks WHERE symbol = ?", (ticker,))
    stock_id_row = cursor.fetchone()

    if stock_id_row:
        stock_id = stock_id_row[0]
        print(f"Ticker {ticker} found with ID {stock_id}. Fetching data...")

        # Fetch all data from the 'prices' table for the given ticker ID
        query = "SELECT date, open, high, low, close, volume FROM prices WHERE stock_id = ?"
        cursor.execute(query, (stock_id,))
        data = cursor.fetchall()

        if data:
            # Convert data to a DataFrame
            df = pd.DataFrame(data, columns=['date', 'open', 'high', 'low', 'close', 'volume'])
            df['date'] = pd.to_datetime(df['date'])
            df.set_index('date', inplace=True)

            # Plotting
            plt.figure(figsize=(10, 6))
            plt.plot(df.index, df['close'], label='Close Price')
            plt.title(f'Stock Price of {ticker} Over Time')
            plt.xlabel('Date')
            plt.ylabel('Price')
            plt.legend()

            # Save the plot
            plot_path = os.path.join(script_dir, f"{ticker}_stock_chart.png")
            plt.savefig(plot_path)
            print(f"Stock chart saved to {plot_path}")
        else:
            print(f"No data found for ticker {ticker} in 'prices' table.")
    else:
        print(f"Ticker {ticker} not found in 'stocks' table.")

    # Close the database connection
    conn.close()

if __name__ == "__main__":
    if len(sys.argv) != 2:
        print("Usage: python check_ticker_data.py <ticker_symbol>")
        sys.exit(1)
    ticker_symbol = sys.argv[1]
    check_ticker_data(ticker_symbol)
