import yfinance as yf
from datetime import datetime
import argparse
from sqlalchemy.orm import sessionmaker
from database import engine, StockData

Session = sessionmaker(bind=engine)

def fetch_and_update_stock_data(ticker, default_start_date='2022-01-01'):
    session = Session()
    records_added = 0
    try:
        # Check the last date in the database for this ticker
        last_record = session.query(StockData).filter(StockData.ticker == ticker).order_by(StockData.date.desc()).first()
        
        start_date = last_record.date.strftime('%Y-%m-%d') if last_record else default_start_date
        print(f"Fetching data for {ticker} starting from {start_date}.")

        # Fetch new data from Yahoo Finance
        end_date = datetime.now().strftime('%Y-%m-%d')
        data = yf.download(ticker, start=start_date, end=end_date)

        if data.empty:
            print(f"No new data available to download for {ticker} since {start_date}.")
            return

        print(f"Data fetched for {ticker}: {len(data)} records.")
        
        # Prepare and insert new data records
        for index, row in data.iterrows():
            stock_data = StockData(ticker=ticker, date=index, open=row['Open'], high=row['High'],
                                   low=row['Low'], close=row['Close'], volume=row['Volume'])
            session.add(stock_data)
            records_added += 1

        session.commit()
        print(f"{records_added} new records added for ticker {ticker}.")
    except Exception as e:
        print(f"An error occurred: {e}")
        session.rollback()
    finally:
        session.close()

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Fetch and update stock data for a given ticker.")
    parser.add_argument("ticker", type=str, help="Stock ticker symbol.")
    parser.add_argument("--start_date", type=str, help="Start date for data fetching in 'YYYY-MM-DD' format.", default='2022-01-01')
    
    args = parser.parse_args()
    fetch_and_update_stock_data(args.ticker, args.start_date)


# command line options:
# python data_acquisition.py AAPL --start_date 2020-01-01
# python data_acquisition.py AAPL

