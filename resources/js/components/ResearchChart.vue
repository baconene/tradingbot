<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
type Point = {time:string;open:number;high:number;low:number;close:number;volume:number;ema20:number|null;ema50:number|null;rsi14:number|null;atr14:number|null;previous20High:number|null;relativeVolume:number|null};
type Marker = {time:string;kind:'entry'|'exit';price:number;pnl:number};
const points=ref<Point[]>([]),markers=ref<Marker[]>([]),busy=ref(false),error=ref(''),updated=ref(''),backtestId=ref<number|null>(null);
let timer:ReturnType<typeof setInterval>|undefined;
const W=1000,H=390,TOP=20,PRICE_H=265,VOL_TOP=295,VOL_H=70;
const bounds=computed(()=>{const values=points.value.flatMap(p=>[p.low,p.high,p.ema20,p.ema50,p.previous20High].filter((v):v is number=>v!==null));const min=Math.min(...values),max=Math.max(...values);return {min:Number.isFinite(min)?min:0,max:Number.isFinite(max)?max:1};});
const x=(i:number)=>30+(i+.5)*940/Math.max(1,points.value.length);
const y=(v:number)=>TOP+PRICE_H-(v-bounds.value.min)/Math.max(1,bounds.value.max-bounds.value.min)*PRICE_H;
const volumeMax=computed(()=>Math.max(1,...points.value.map(p=>p.volume)));
const line=(key:'ema20'|'ema50'|'previous20High')=>points.value.map((p,i)=>p[key]===null?'':`${x(i)},${y(p[key]!)}`).filter(Boolean).join(' ');
const rsiLine=computed(()=>points.value.map((p,i)=>p.rsi14===null?'':`${x(i)},${92-(p.rsi14/100)*80}`).filter(Boolean).join(' '));
const visibleMarkers=computed(()=>markers.value.map(m=>({...m,index:points.value.findIndex(p=>p.time===m.time)})).filter(m=>m.index>=0));
const last=computed(()=>points.value.at(-1));
const selected=ref<number|null>(null);
const hover=computed(()=>points.value[selected.value??points.value.length-1]);
const timeLabel=(v?:string)=>v?new Date(v).toLocaleString('en-US',{timeZone:'UTC',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',hour12:false})+' UTC':'—';
async function refresh(){if(busy.value)return;busy.value=true;try{const response=await fetch('/api/chart',{headers:{Accept:'application/json'}});if(!response.ok)throw Error('HTTP '+response.status);const data=await response.json();points.value=data.points;markers.value=data.markers;backtestId.value=data.backtest_id;updated.value=new Date().toLocaleTimeString();error.value='';}catch(e){error.value='Chart unavailable: '+String(e);}finally{busy.value=false;}}
onMounted(()=>{void refresh();timer=setInterval(()=>void refresh(),60000);});
onUnmounted(()=>{if(timer)clearInterval(timer);});
</script>
<template>
<section class="panel astra-chart">
  <div class="panelhead"><div><h3>BTCUSDT · 1H historical chart</h3><p class="chart-note">Completed candles · refreshes every 60 seconds · not a tick-level live feed</p></div><button class="chart-refresh" type="button" :disabled="busy" @click="refresh">{{busy?'Refreshing…':'Refresh'}}</button></div>
  <p v-if="error" class="warning" role="alert">{{error}}</p>
  <div class="chart-legend"><span class="legend-price">Price</span><span class="legend-ema20">EMA 20</span><span class="legend-ema50">EMA 50</span><span class="legend-breakout">Previous 20 high</span><span class="legend-entry">▲ Backtest entry</span><span class="legend-exit">▼ Backtest exit</span></div>
  <div v-if="points.length" class="chart-scroll">
    <svg viewBox="0 0 1000 390" role="img" aria-label="Hourly BTCUSDT candlesticks with EMA 20, EMA 50, previous 20 high, historical backtest markers and volume">
      <g v-for="tick in 5" :key="tick"><line x1="30" :y1="20+(tick-1)*66.25" x2="975" :y2="20+(tick-1)*66.25" stroke="#2b4148" stroke-dasharray="3 5"/><text x="2" :y="24+(tick-1)*66.25" fill="#9ab6b5" font-size="9">{{(bounds.max-(tick-1)*(bounds.max-bounds.min)/4).toFixed(0)}}</text></g>
      <g v-for="(p,i) in points" :key="p.time"><line :x1="x(i)" :x2="x(i)" :y1="y(p.high)" :y2="y(p.low)" :stroke="p.close>=p.open?'#7ad6b4':'#e38d87'" stroke-width="1"/><rect :x="x(i)-Math.max(1,370/points.length)" :y="Math.min(y(p.open),y(p.close))" :width="Math.max(2,740/points.length)" :height="Math.max(1,Math.abs(y(p.open)-y(p.close)))" :fill="p.close>=p.open?'#7ad6b4':'#e38d87'"/><rect :x="x(i)-Math.max(1,370/points.length)" :y="VOL_TOP+VOL_H-p.volume/volumeMax*VOL_H" :width="Math.max(2,740/points.length)" :height="p.volume/volumeMax*VOL_H" :fill="p.close>=p.open?'#397c71':'#965b61'" opacity=".8"/></g>
      <polyline :points="line('ema20')" fill="none" stroke="#e4c278" stroke-width="1.7"/><polyline :points="line('ema50')" fill="none" stroke="#8eb9f3" stroke-width="1.7"/><polyline :points="line('previous20High')" fill="none" stroke="#bd9ee8" stroke-width="1.2" stroke-dasharray="5 4"/>
      <g v-for="(m,i) in visibleMarkers" :key="i"><path :d="m.kind==='entry'?`M ${x(m.index)} ${y(m.price)+6} l -5 9 h 10 Z`:`M ${x(m.index)} ${y(m.price)-6} l -5 -9 h 10 Z`" :fill="m.kind==='entry'?'#64e0bd':'#f5b17e'"><title>{{m.kind}} · {{m.price.toFixed(2)}} · trade P&amp;L {{m.pnl.toFixed(2)}} USDT</title></path></g>
      <rect x="30" y="20" width="945" height="345" fill="transparent" @mousemove="(e:MouseEvent)=>{const r=(e.currentTarget as SVGRectElement).getBoundingClientRect();selected=Math.max(0,Math.min(points.length-1,Math.floor(((e.clientX-r.left)/r.width)*points.length)));}" @mouseleave="selected=null"/>
      <text x="32" y="383" fill="#91aaa8" font-size="11">VOLUME</text><text x="970" y="383" text-anchor="end" fill="#91aaa8" font-size="11">{{timeLabel(last?.time)}}</text>
    </svg>
  </div>
  <p v-else-if="!busy" class="muted">No chart data yet. Complete the historical candle backfill.</p>
  <div v-if="hover" class="chart-readout"><span>{{timeLabel(hover.time)}}</span><span>O {{hover.open.toFixed(2)}}</span><span>H {{hover.high.toFixed(2)}}</span><span>L {{hover.low.toFixed(2)}}</span><span>C {{hover.close.toFixed(2)}}</span><span>RSI 14 {{hover.rsi14?.toFixed(1)??'—'}}</span><span>ATR 14 {{hover.atr14?.toFixed(2)??'—'}}</span></div>
  <div class="chart-footer"><span>Latest refresh: {{updated||'waiting'}} · Historical backtest {{backtestId?'#'+backtestId:'not run'}}</span><span>Backtest markers reflect historical simulations, not actual orders</span></div>
  <div class="chart-rsi"><div class="chart-rsi-head">RSI 14 · latest {{last?.rsi14?.toFixed(1)??'—'}}</div><svg viewBox="0 0 1000 100" role="img" aria-label="RSI 14 indicator"><line x1="30" y1="36" x2="975" y2="36" stroke="#956d6d" stroke-dasharray="4 5"/><line x1="30" y1="68" x2="975" y2="68" stroke="#956d6d" stroke-dasharray="4 5"/><text x="2" y="39" fill="#9ab6b5" font-size="11">70</text><text x="2" y="71" fill="#9ab6b5" font-size="11">30</text><polyline :points="rsiLine" fill="none" stroke="#bba0ee" stroke-width="2"/></svg></div>
</section>
</template>
