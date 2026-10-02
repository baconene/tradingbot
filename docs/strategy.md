# MBR-001 — Approved research specification
- Market: BTCUSDT spot, 1-hour completed candles; production prices for signals; Binance Spot Testnet for virtual orders.
- Starting reference capital: 1,000 USDT.
- Long-only, no leverage; no live-money trading.
- Entry hypothesis: close above previous 20-candle high, EMA20 > EMA50, RSI14 55–75, relative volume >= 1.5.
- Proposed stop: 1.5 × ATR14; proposed target: 2R; evaluate exit after six completed hours.
- Risk per trade: 0.5%; daily loss limit: 2%; max drawdown: 10%; max position: 25%; portfolio exposure: 50%; manual approval > 100 USDT.
- These are research hypotheses. No backtests have been run in this Laravel scaffold.
- Trading and Binance integration are disabled.
