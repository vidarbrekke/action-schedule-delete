import yfinance as yf
import sqlite3
from sqlite3 import Error

class StockDataRetriever:
    def __init__(self, db_path, tickers, start_date, end_date):
        self.db_path = db_path
        self.tickers = tickers
        self.start_date = start_date
        self.end_date = end_date

    def fetch_data(self):
        for ticker in self.tickers:
            data = yf.download(ticker, start=self.start_date, end=self.end_date)
            self.store_data(ticker, data)

    def store_data(self, ticker, data):
        try:
            conn = sqlite3.connect(self.db_path)
            cursor = conn.cursor()
            for index, row in data.iterrows():
                date = index.strftime('%Y-%m-%d')
                values = (date, ticker, row['Open'], row['High'], row['Low'], row['Close'], row['Adj Close'], row['Volume'])
                cursor.execute('''
                    INSERT INTO STOCK_DATA (DATE, TICKER, OPEN, HIGH, LOW, CLOSE, ADJ_CLOSE, VOLUME)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ''', values)
            conn.commit()
        except Error as e:
            print(f"Error inserting data for {ticker}: {e}")
        finally:
            conn.close()

    def run(self):
        self.fetch_data()
        print("Data retrieval and storage complete for all tickers.")

# Define the database path and tickers outside the class
db_path = 'data/stock_data.db'
tickers = ['AMD', 'BAC', 'PFE', 'F', 'XOM', 'PLTR', 'MRNA']
start_date = '2020-01-01'
end_date = '2023-01-01'

# Create an instance of the data retriever and run it
data_retriever = StockDataRetriever(db_path, tickers, start_date, end_date)
data_retriever.run()
