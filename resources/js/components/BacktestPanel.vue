<script setup lang="ts">
import {computed,onMounted,ref} from 'vue';
type Trade={entry_index:number;exit_index:number;entry:number;exit:number;quantity:number;pnl:number;exit_reason:string};
type Run={id:number;data_start:string;data_end:string;results:{trade_count:number;win_rate_pct:number|null;realized_return_pct:number;max_realized_drawdown_pct:number;final_realized_equity:number;trades:Trade[]}};
const run=ref<Run|null>(null),error=ref(''),loading=ref(false),filter=ref('all');
const trades=computed(()=>[...(run.value?.results.trades??[])].map((t,i)=>({...t,id:i+1})).filter(t=>filter.value==='all'||(filter.value==='wins'?t.pnl>0:t.pnl<=0)).reverse());
const fmt=(n:number|null|undefined,d=2)=>n==null?'—':n.toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d});
const timestamp=(index:number)=>run.value?new Date(new Date(run.value.data_start.replace(' ','T')+'Z').getTime()+index*3600000).toISOString():'';
const date=(index:number)=>new Date(timestamp(index)).toLocaleString('en-US',{timeZone:'UTC',month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:false})+' UTC';
function jump(index:number){window.dispatchEvent(new CustomEvent('astra:chart-jump',{detail:timestamp(index)}));document.getElementById('research-chart')?.scrollIntoView({behavior:'smooth'});}
async function refresh(){loading.value=true;try{const response=await fetch('/api/research/backtests');if(!response.ok)throw Error('HTTP '+response.status);run.value=(await response.json()).latest_backtest;error.value='';}catch(e){error.value=String(e);}finally{loading.value=false;}}
onMounted(()=>void refresh());
</script>
<template>
<section class="panel backtest-panel"><div class="panelhead"><div><div class="eyebrow">MILESTONE 3 / HISTORICAL SIMULATION</div><h3>Backtest trade ledger</h3><p class="chart-note">Saved backtest results · simulated fills, not real orders</p></div><button class="chart-refresh" type="button" :disabled="loading" @click="refresh">Refresh results</button></div>
<p v-if="error" class="warning">{{error}}</p>
<div v-if="!run" class="backtest-empty"><h3>No saved backtest</h3><p>Run <code>php artisan astra:backtest</code> after importing continuous historical candles. The trade ledger will appear here.</p></div>
<template v-else>
<p class="backtest-meta">Run #{{run.id}} · {{run.data_start}} — {{run.data_end}} UTC</p>
<div class="backtest-stats"><div><span>Completed trades</span><strong>{{run.results.trade_count}}</strong></div><div><span>Realized return</span><strong :class="run.results.realized_return_pct>=0?'profit':'loss'">{{fmt(run.results.realized_return_pct)}}%</strong></div><div><span>Win rate</span><strong>{{fmt(run.results.win_rate_pct)}}%</strong></div><div><span>Max realized drawdown</span><strong>{{fmt(run.results.max_realized_drawdown_pct)}}%</strong></div><div><span>Final realized equity</span><strong>{{fmt(run.results.final_realized_equity)}} USDT</strong></div></div>
<div class="backtest-controls"><label for="trade-filter">Filter trades</label><select id="trade-filter" v-model="filter"><option value="all">All</option><option value="wins">Profitable</option><option value="losses">Unprofitable</option></select><span>{{trades.length}} shown · newest first</span></div>
<p v-if="!trades.length" class="backtest-empty">{{run.results.trade_count===0?'This backtest generated no completed trades.':'No trades match the filter.'}}</p>
<div v-else class="backtest-table-wrap"><table class="backtest-table"><thead><tr><th>#</th><th>Entry time</th><th>Entry price</th><th>Exit time</th><th>Exit price</th><th>Exit reason</th><th>BTC qty</th><th>Net P&amp;L</th><th>On chart</th></tr></thead><tbody><tr v-for="t in trades" :key="t.id"><td data-label="Trade #">{{t.id}}</td><td data-label="Entry time">{{date(t.entry_index)}}</td><td data-label="Entry price">{{fmt(t.entry)}}</td><td data-label="Exit time">{{date(t.exit_index)}}</td><td data-label="Exit price">{{fmt(t.exit)}}</td><td data-label="Exit reason">{{t.exit_reason}}</td><td data-label="BTC quantity">{{fmt(t.quantity,6)}}</td><td data-label="Net P&L" :class="t.pnl>=0?'profit':'loss'">{{t.pnl>=0?'+':''}}{{fmt(t.pnl)}} USDT</td><td data-label="On chart"><button type="button" class="chart-trade" @click="jump(t.entry_index)">Entry ↗</button><button type="button" class="chart-trade" @click="jump(t.exit_index)">Exit ↗</button></td></tr></tbody></table></div>
</template></section>
</template>
