# update_stock_data.py

import os
import sys
import yfinance as yf
import sqlite3
import pandas as pd
import datetime

import datetime

def fetch_data_from_yfinance(ticker, last_update_date):
    max_days = 729  # yfinance maximum allowed days for hourly data
    end_date = datetime.datetime.now()

    if last_update_date is None:
        start_date = end_date - datetime.timedelta(days=max_days)
    else:
        # Convert last_update_date from string to datetime
        last_update_date = datetime.datetime.strptime(last_update_date, '%Y-%m-%d %H:%M:%S')
        # Calculate days difference
        days_diff = (end_date - last_update_date).days

        if days_diff > max_days:
            start_date = end_date - datetime.timedelta(days=max_days)
        else:
            start_date = last_update_date

    interval = '1h'
    stock_data = yf.download(ticker, start=start_date.strftime('%Y-%m-%d'), end=end_date.strftime('%Y-%m-%d'), interval=interval)
    return stock_data


def update_database_with_data(conn, ticker_id, stock_data):
    insert_sql = """
        INSERT INTO prices (stock_id, date, open, high, low, close, volume)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON CONFLICT(stock_id, date) DO UPDATE SET
        open=excluded.open,
        high=excluded.high,
        low=excluded.low,
        close=excluded.close,
        volume=excluded.volume;
    """
    stock_data.reset_index(inplace=True)
    for _, row in stock_data.iterrows():
        conn.execute(insert_sql, (
            ticker_id,
            row['Datetime'].strftime('%Y-%m-%d %H:%M:%S'),
            row['Open'],
            row['High'],
            row['Low'],
            row['Close'],
            row['Volume']
        ))

def main(ticker):
    script_dir = os.path.dirname(os.path.abspath(__file__))
    database_path = os.path.join(script_dir,"trading_app.db")

    conn = sqlite3.connect(database_path)
    last_update_query = """
        SELECT MAX(date) FROM prices WHERE stock_id = (
            SELECT id FROM stocks WHERE symbol = ?
        )
    """
    cursor = conn.cursor()
    cursor.execute(last_update_query, (ticker,))
    last_update_result = cursor.fetchone()[0]
    last_update_date = None if last_update_result is None else datetime.datetime.strptime(last_update_result, '%Y-%m-%d %H:%M:%S')

    stock_data = fetch_data_from_yfinance(ticker, last_update_date)

    cursor.execute("SELECT id FROM stocks WHERE symbol = ?", (ticker,))
    ticker_id = cursor.fetchone()
    if ticker_id is None:
        conn.execute("INSERT INTO stocks (symbol) VALUES (?)", (ticker,))
        ticker_id = cursor.lastrowid
    else:
        ticker_id = ticker_id[0]

    update_database_with_data(conn, ticker_id, stock_data)

    conn.commit()
    conn.close()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python update_stock_data.py <ticker>")
        sys.exit(1)
    ticker = sys.argv[1]
    main(ticker)
