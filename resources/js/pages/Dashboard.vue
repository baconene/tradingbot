<script setup lang="ts">
import {computed,onMounted,onUnmounted,ref} from 'vue';
import CandleChart,{type Candle} from '../components/CandleChart.vue';
type Market={symbol:string;market:string;source:string;stale:boolean;contiguous:boolean;last_candle_ms:number|null;timeframes:Record<string,Candle[]>};
type Metrics={initial_equity:number;final_equity:number;net_return_pct:number;max_realized_drawdown_pct:number;trades:number;wins:number;losses:number;win_rate_pct:number|null;rr_target:number;average_net_r:number|null;total_fees:number;funding_included:boolean;liquidation_modeled:boolean};
type Trade={id:number;side:string;signal_ms:number;entry_ms:number;exit_ms:number;entry:number;stop:number;target:number;exit:number;quantity:number;net_pnl:number;fees:number;r_multiple:number;exit_reason:string};
type Result={run:null|{id:number;symbol:string;strategy:string;parameters:Record<string,number>;metrics:Metrics;bars:number;first_open_ms:number;last_open_ms:number;created_at:string};trades:Trade[];equity_curve:{ms:number;equity:number}[]};
const operatorToken=ref(''),importPages=ref(2),backtestBars=ref(15000),rr=ref(2),risk=ref(0.005),busy=ref<'import'|'backtest'|'live'|null>(null),autoLive=ref(false),liveStatus=ref('Not started'),operationMessage=ref('');
const market=ref<Market|null>(null),result=ref<Result|null>(null),loading=ref(true),error=ref(''),selected=ref<number|null>(null);
const frames=computed(()=>market.value?.timeframes??{'1m':[],'5m':[],'15m':[]});
const metrics=computed(()=>result.value?.run?.metrics??null);
const fmt=(n:number|null|undefined,d=2)=>n==null?'—':n.toLocaleString(undefined,{minimumFractionDigits:d,maximumFractionDigits:d});
const when=(ms:number)=>new Date(ms).toLocaleString();
const equityPoints=computed(()=>{const points=result.value?.equity_curve??[];if(points.length<2)return '';
 const lo=Math.min(...points.map(p=>p.equity)),hi=Math.max(...points.map(p=>p.equity)),span=Math.max(0.001,hi-lo);
 return points.map((p,i)=>(10+i/(points.length-1)*580)+','+(175-(p.equity-lo)/span*155)).join(' ');
});
const latestSetup=computed(()=>{
 const five=frames.value['5m']??[],fifteen=frames.value['15m']??[];const s=five[five.length-1];
 const t=fifteen.filter(b=>s&&b.close_ms<=s.close_ms).at(-1);
 if(!s||!t||s.ema20==null||s.ema50==null||t.ema50==null||s.previous_high==null||s.previous_low==null)return null;
 const side=s.close>s.previous_high&&s.ema20>s.ema50&&t.close>t.ema50?'LONG':
 s.close<s.previous_low&&s.ema20<s.ema50&&t.close<t.ema50?'SHORT':null;
 if(!side)return null;
 const stop=side==='LONG'?s.previous_low:s.previous_high;
 return {side,signal_ms:s.open_ms,entry_reference:s.close,stop,target:s.close+(side==='LONG'?1:-1)*Math.abs(s.close-stop)*2};
});
let timer:ReturnType<typeof setInterval>|undefined;
let liveTimer:ReturnType<typeof setInterval>|undefined;
function toggleLive(){
 if(autoLive.value){autoLive.value=false;if(liveTimer)clearInterval(liveTimer);liveTimer=undefined;liveStatus.value='Auto-cycle stopped';return;}
 if(!operatorToken.value){liveStatus.value='Enter the operator token first';return;}
 autoLive.value=true;liveStatus.value='Auto-cycle enabled while this tab remains open';
 void runOperation('live');
 liveTimer=setInterval(()=>{if(autoLive.value&&!document.hidden&&!busy.value)void runOperation('live');},90000);
}
async function refresh(){try{const [m,r]=await Promise.all([fetch('/api/futures/market',{cache:'no-store'}),fetch('/api/research/backtest',{cache:'no-store'})]);if(!m.ok||!r.ok)throw Error('Market or backtest endpoint unavailable');market.value=await m.json() as Market;result.value=await r.json() as Result;error.value='';}catch(e){error.value=String(e);}finally{loading.value=false;}}
async function runOperation(kind:'import'|'backtest'|'live'){
 if(busy.value)return;
 busy.value=kind;operationMessage.value='';
 try{
  const csrf=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
  const endpoint=kind==='import'?'/api/research/import':kind==='live'?'/api/research/live-backtest':'/api/research/run';
  const body=kind==='import'?{pages:importPages.value}:kind==='live'?{pages:importPages.value,bars:backtestBars.value,rr:rr.value,risk:risk.value}:{bars:backtestBars.value,rr:rr.value,risk:risk.value};
  const response=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'Authorization':'Bearer '+operatorToken.value},body:JSON.stringify(body)});
  const data=await response.json();
  if(!response.ok)throw new Error((data.message??'Research operation failed')+(data.available_candles!=null?' Available: '+data.available_candles+' candles.':''));
  operationMessage.value=kind==='import'?('Imported '+data.imported+' candles; observed gaps: '+data.gaps_observed):kind==='live'?(data.message??('Synced '+data.import.imported+' new candles. Backtest #'+data.run_id+' completed.')):('Backtest #'+data.run_id+' completed. '+data.trades+' trades.');
  if(kind==='live')liveStatus.value='Last successful cycle: '+new Date().toLocaleTimeString()+' · '+(data.status==='unchanged'?'No new candles':'Run #'+data.run_id);
  await refresh();
 }catch(e){operationMessage.value=String(e);if(kind==='live'){liveStatus.value='Cycle failed: '+String(e);autoLive.value=false;}}finally{busy.value=null;}
}
function jump(trade:Trade){selected.value=trade.entry_ms;document.querySelector('#charts')?.scrollIntoView({behavior:'smooth'});}
onMounted(()=>{void refresh();timer=setInterval(()=>{if(!document.hidden)void refresh();},30000);});
onUnmounted(()=>{if(timer)clearInterval(timer);if(liveTimer)clearInterval(liveTimer);});
</script>
<template>
<main class="shell">
 <header class="topbar"><div class="brand"><span class="brandmark">A</span><div><span class="eyebrow">ASTRA / BINANCE USDⓈ-M FUTURES</span><h1>Scalping research terminal</h1></div></div><div class="header-right"><span class="pill lock">RESEARCH ONLY</span><span class="pill" :class="market&&!market.stale?'healthy':'stale'">{{market&&!market.stale?'Data current':'Data unavailable / stale'}}</span><button @click="refresh">Refresh</button></div></header>
 <div class="notice">No orders are submitted. Signals are historical research, not live trading advice. Binance Futures access from Forge must be verified.</div>
 <p v-if="error" class="error" role="alert">{{error}}</p>
 <section class="panel operator-panel" aria-label="Research controls"><div class="section-title"><div><small>UI CONTROLLED · NO FORGE COMMANDS</small><h2>Research controls</h2></div><span class="pill lock">OPERATOR ONLY</span></div>
  <p class="muted">Enter the research operator token configured once in Forge Environment. The token stays in this page's memory and is not saved in your browser.</p>
  <div class="operator-grid"><label>Operator token<input v-model="operatorToken" type="password" autocomplete="off" placeholder="Research operator token" /></label>
   <label>Import pages (up to 2, 1,000 candles each)<select v-model.number="importPages"><option :value="1">1 page</option><option :value="2">2 pages</option></select></label>
   <button :disabled="!operatorToken||busy!==null" @click="runOperation('import')">{{busy==='import'?'Importing…':'Import futures candles'}}</button>
  </div>
  <div class="operator-grid"><label>Backtest candles<select v-model.number="backtestBars"><option :value="900">900</option><option :value="3000">3,000</option><option :value="7500">7,500</option><option :value="15000">15,000</option></select></label>
   <label>Reward / risk<select v-model.number="rr"><option :value="1">1:1</option><option :value="1.5">1.5:1</option><option :value="2">2:1</option><option :value="3">3:1</option></select></label>
   <label>Risk per trade<select v-model.number="risk"><option :value="0.0025">0.25%</option><option :value="0.005">0.50%</option><option :value="0.01">1.00%</option></select></label>
   <button :disabled="!operatorToken||busy!==null" @click="runOperation('backtest')">{{busy==='backtest'?'Backtesting…':'Run backtest'}}</button>
  </div><div class="live-controls"><div><strong>Rolling Binance Futures backtest</strong><p class="muted">Sync newly closed 1m candles, rerun the 5m/15m 2R strategy, and refresh the ledger. Auto-cycle runs every 90 seconds while this tab is open; it stops on errors or when the tab is closed.</p><small>{{liveStatus}}</small></div><button :disabled="!operatorToken||busy!==null" @click="runOperation('live')">{{busy==='live'?'Syncing & testing…':'Sync & backtest now'}}</button><button :disabled="!operatorToken" @click="toggleLive">{{autoLive?'Stop auto-cycle':'Start auto-cycle'}}</button></div><p v-if="operationMessage" role="status" class="operation-message">{{operationMessage}}</p>
 </section>
 <section class="summary">
  <article><small>MARKET</small><strong>{{market?.symbol??'BTCUSDT'}}</strong><span>USDⓈ-M perpetual</span></article>
  <article><small>DATA INTEGRITY</small><strong>{{market?.contiguous?'Contiguous':'Check gaps'}}</strong><span>{{market?.last_candle_ms?when(market.last_candle_ms):'No import yet'}}</span></article>
  <article><small>BACKTEST WIN RATE</small><strong>{{fmt(metrics?.win_rate_pct)}}{{metrics?.win_rate_pct!=null?'%':''}}</strong><span>{{metrics?.trades??0}} closed trades</span></article>
  <article><small>NET RETURN</small><strong :class="(metrics?.net_return_pct??0)>=0?'positive':'negative'">{{fmt(metrics?.net_return_pct)}}{{metrics?'%':''}}</strong><span>After simulated fees/slippage</span></article>
 </section>
 <section id="charts" class="charts"><CandleChart title="1 MINUTE" subtitle="Entry timing · EMA 20 / 50 · RSI · ATR" :bars="frames['1m']??[]" :selected-ms="selected" @select="selected=$event"/><CandleChart title="5 MINUTES" subtitle="HH/LL breakout setup · previous 20 highs/lows" :bars="frames['5m']??[]" :selected-ms="selected" @select="selected=$event"/><CandleChart title="15 MINUTES" subtitle="Directional filter · EMA 20 / 50" :bars="frames['15m']??[]" :selected-ms="selected" @select="selected=$event"/></section>
 <section class="split">
  <article class="panel"><div class="section-title"><div><small>MARKET STRUCTURE</small><h2>Latest confirmed 5m setup</h2></div><span class="pill">2 : 1 RR</span></div>
   <div v-if="latestSetup&&!market?.stale&&market?.contiguous" class="signal">
    <strong :class="latestSetup.side==='LONG'?'positive':'negative'">{{latestSetup.side}} · historical reference</strong>
    <div class="signal-levels"><div><small>REFERENCE CLOSE</small><strong>{{fmt(latestSetup.entry_reference)}}</strong></div><div><small>STOP</small><strong>{{fmt(latestSetup.stop)}}</strong></div><div><small>2R TARGET</small><strong>{{fmt(latestSetup.target)}}</strong></div></div>
    <p>Signal candle: {{when(latestSetup.signal_ms)}}. This is not a forecast or executable entry; the backtester enters on the next 1m open.</p>
   </div><p v-else class="empty">No confirmed aligned HH/LL breakout on the latest 5m candle, or market data is incomplete/stale.</p>
  </article>
  <article class="panel"><div class="section-title"><div><small>BACKTEST PERFORMANCE</small><h2>Realized equity</h2></div><span class="pill">{{result?.run?'RUN #'+result.run.id:'NOT RUN'}}</span></div>
   <svg v-if="equityPoints" viewBox="0 0 600 190" class="equity" role="img" aria-label="Historical realized equity curve"><line x1="10" x2="590" y1="175" y2="175" stroke="#52647a"/><polyline :points="equityPoints" stroke="#59d7b1" stroke-width="2.5" fill="none"/></svg>
   <p v-else class="empty">Use Research Controls above to import candles and run your first backtest.</p>
   <div class="metrics"><div><small>MAX REALIZED DD</small><strong>{{fmt(metrics?.max_realized_drawdown_pct)}}%</strong></div><div><small>AVG NET R</small><strong>{{fmt(metrics?.average_net_r)}}R</strong></div><div><small>FEES</small><strong>{{fmt(metrics?.total_fees)}} USDT</strong></div><div><small>RR TARGET</small><strong>{{fmt(metrics?.rr_target)}}:1</strong></div></div>
  </article>
 </section>
 <section class="panel ledger"><div class="section-title"><div><small>AUDITABLE RESEARCH</small><h2>Backtesting ledger</h2></div><span class="pill">{{result?.run?.strategy??'Awaiting first run'}}</span></div>
  <p class="muted">Each row is a simulated trade from completed Binance Futures candles. Select a row to locate its entry on the charts. Funding and liquidation are not yet modeled.</p>
  <div class="table-scroll"><table><thead><tr><th>Entry time</th><th>Side</th><th>Entry</th><th>Stop</th><th>2R target</th><th>Exit</th><th>Reason</th><th>Fees</th><th>Net P&L</th><th>Net R</th></tr></thead><tbody>
   <tr v-for="t in result?.trades??[]" :key="t.id" tabindex="0" @click="jump(t)" @keydown.enter="jump(t)"><td>{{when(t.entry_ms)}}</td><td :class="t.side==='long'?'positive':'negative'">{{t.side.toUpperCase()}}</td><td>{{fmt(t.entry)}}</td><td>{{fmt(t.stop)}}</td><td>{{fmt(t.target)}}</td><td>{{fmt(t.exit)}}</td><td>{{t.exit_reason}}</td><td>{{fmt(t.fees,3)}}</td><td :class="t.net_pnl>=0?'positive':'negative'">{{fmt(t.net_pnl,3)}}</td><td>{{fmt(t.r_multiple,3)}}</td></tr>
   <tr v-if="!result?.trades?.length"><td colspan="10" class="empty">No simulated trades recorded yet.</td></tr>
  </tbody></table></div>
 </section>
 <footer>Phase 1–3 research build · 1m/5m/15m charts · EMA / RSI / ATR / HH / LL · stop-first OHLC assumptions · No ML forecast or live execution</footer>
</main>
</template>