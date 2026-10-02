# TradingView-style research chart navigation

The custom SVG research chart supports pointer dragging, scroll-to-pan, Ctrl/Command + scroll zoom, +/- zoom buttons, older-history pagination, a latest shortcut, crosshair OHLC inspection, and jump-to-backtest-entry buttons. It is a TradingView-inspired interaction, not TradingView's licensed charting library.

The read-only `GET /api/chart?before=<UTC Unix seconds>` returns up to 240 historical candles and 250 preceding warmup candles for indicators. It also returns historical trade markers from the latest saved backtest. No chart action places an order. When scrolling backward, the client requests earlier history; markers outside the current viewport can be reached with the backtest trade buttons.

A historical marker's timestamp is derived from the saved backtest's first candle plus its entry/exit index. This assumes continuous 1h history, which the backtester validates. Historical backtest markers are simulated, not executed orders. The chart refreshes the latest view every 60 seconds, based on stored completed hourly candles only.

Acceptance requires browser testing of drag, wheel, touch, zoom and backtest jumps on Forge. A full TradingView-grade experience would require a purpose-built charting library and further accessibility work.
