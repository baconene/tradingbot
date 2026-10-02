<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
type Risk = { riskPerTrade:number; dailyLossLimit:number; drawdownLimit:number; maxPositionNotional:number; maxPortfolioExposure:number; manualApprovalUsdt:number; killSwitch:string; tradingEnabled:boolean };
type Features = {close:number;ema20:number;ema50:number;rsi14:number;atr14:number;previous_20_high:number;relative_volume:number|null;spread_bps:number|null};
const props = defineProps<{ project:{name:string;mode:string;symbol:string;timeframe:string;capital:number;currency:string}; risk:Risk; services:Record<string,string>; market:{candleCount:number;latestClose:number|null;latestCandle:string|null;fresh:boolean;snapshot:Features|null} }>();
const riskUsdt=computed(()=>(props.project.capital*props.risk.riskPerTrade).toFixed(2));
const fmt=(n:number|null|undefined,d=2)=>n==null?'—':n.toLocaleString('en-US',{maximumFractionDigits:d,minimumFractionDigits:d});
</script>
<template>
  <Head title="GPT Astra | Market Research" />
  <main class="shell">
    <header class="top"><div><div class="eyebrow">QUANT RESEARCH TERMINAL</div><h1>GPT <span>ASTRA</span></h1></div><div class="tag">PAPER · EXECUTION LOCKED</div></header>
    <section class="hero"><div><p class="eyebrow">MILESTONE 2 / MARKET DATA</p><h2>Observe first.<br/>Trade only when proven.</h2><p class="muted">BTCUSDT · 1-hour momentum / breakout · Production public market data</p></div><div class="orb"><div class="orbinner">A</div></div></section>
    <section class="metrics"><article><div class="eyebrow">SIMULATED STARTING CAPITAL</div><strong>{{ project.capital.toLocaleString() }} <small>USDT</small></strong><p>Reference capital, not a live balance</p></article><article><div class="eyebrow">MAX RISK PER TRADE</div><strong>{{ riskUsdt }} <small>USDT</small></strong><p>0.5% of initial capital</p></article><article><div class="eyebrow">HOURLY CANDLES STORED</div><strong>{{ market.candleCount.toLocaleString() }}</strong><p>Validated completed BTCUSDT candles</p></article><article><div class="eyebrow">LATEST CLOSE</div><strong>{{ fmt(market.latestClose) }} <small>USDT</small></strong><p>{{ market.latestCandle ?? 'Awaiting first sync' }}</p></article></section>
    <section class="panels"><article class="panel"><div class="panelhead"><h3>Market data</h3><span :class="market.fresh?'subtle':'warning'">{{ market.fresh?'FRESH':'STALE / NOT READY' }}</span></div><div class="status"><span>Source</span><b>Binance public spot</b></div><div class="status"><span>Timeframe</span><b>1 hour</b></div><div class="status"><span>EMA 20 / EMA 50</span><b>{{ fmt(market.snapshot?.ema20) }} / {{ fmt(market.snapshot?.ema50) }}</b></div><div class="status"><span>RSI 14 / ATR 14</span><b>{{ fmt(market.snapshot?.rsi14) }} / {{ fmt(market.snapshot?.atr14) }}</b></div><div class="status"><span>Previous 20 high</span><b>{{ fmt(market.snapshot?.previous_20_high) }}</b></div><div class="status"><span>Relative volume</span><b>{{ fmt(market.snapshot?.relative_volume) }}</b></div><div class="status"><span>Historical spread</span><b>Unavailable · no estimate</b></div></article>
    <article class="panel"><div class="panelhead"><h3>System readiness</h3><span class="warning">RESEARCH ONLY</span></div><div class="status" v-for="(state,name) in services" :key="name"><span>{{ String(name).replace('_',' ') }}</span><span class="state">{{ state.replaceAll('_',' ') }}</span></div><div class="status"><span>Strategy execution</span><span class="locked">DISABLED</span></div><div class="status"><span>Daily loss limit</span><b>{{ (risk.dailyLossLimit*100).toFixed(0) }}%</b></div><div class="status"><span>Drawdown kill switch</span><b>{{ (risk.drawdownLimit*100).toFixed(0) }}%</b></div><div class="status"><span>Kill switch</span><span class="locked">LOCKED</span></div></article></section>
    <footer>GPT ASTRA / MARKET DATA IS NOT A TRADING SIGNAL · NO LIVE TRADING</footer>
  </main>
</template>
