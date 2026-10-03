<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
type Point={time:string;open:number;high:number;low:number;close:number;volume:number;ema20:number|null;ema50:number|null;rsi14:number|null;atr14:number|null;previous20High:number|null;relativeVolume:number|null};
type Marker={time:string;kind:'entry'|'exit';price:number;pnl:number};
const points=ref<Point[]>([]),markers=ref<Marker[]>([]),busy=ref(false),error=ref(''),updated=ref(''),backtestId=ref<number|null>(null),hasMore=ref(false);
const count=ref(90),end=ref(0),hoverIndex=ref<number|null>(null),drag=ref<{x:number;end:number}|null>(null);
let timer:ReturnType<typeof setInterval>|undefined;
const indicatorsOpen=ref(false),tradesOpen=ref(false);
const atLatest=computed(()=>end.value>=points.value.length);
function zoom(delta:number){count.value=Math.max(20,Math.min(220,count.value+delta));end.value=Math.max(Math.min(points.value.length,end.value),Math.min(count.value,points.value.length));}
function jumpBack(){void pan(-Math.max(8,Math.floor(count.value/2)));}
function jumpForward(){void pan(Math.max(8,Math.floor(count.value/2)));}
const W=1000,LEFT=65,RIGHT=970,TOP=20,PRICE_BOTTOM=275,VOL_TOP=305,VOL_H=62;
const start=computed(()=>Math.max(0,end.value-count.value));
const visible=computed(()=>points.value.slice(start.value,end.value));
const latest=computed(()=>points.value.at(-1));
const hovered=computed(()=>points.value[hoverIndex.value??Math.max(0,end.value-1)]);
const bounds=computed(()=>{const vals=visible.value.flatMap(p=>[p.low,p.high,p.ema20,p.ema50,p.previous20High].filter((v):v is number=>v!==null));const min=Math.min(...vals),max=Math.max(...vals);const pad=Math.max(1,(max-min)*.08);return {min:Number.isFinite(min)?min-pad:0,max:Number.isFinite(max)?max+pad:1};});
const x=(i:number)=>LEFT+(i+.5)*(RIGHT-LEFT)/Math.max(1,visible.value.length);
const y=(v:number)=>TOP+(PRICE_BOTTOM-TOP)*(bounds.value.max-v)/Math.max(.0001,bounds.value.max-bounds.value.min);
const volumeMax=computed(()=>Math.max(1,...visible.value.map(p=>p.volume)));
const path=(key:'ema20'|'ema50'|'previous20High')=>visible.value.map((p,i)=>p[key]===null?'':`${x(i)},${y(p[key]!)}`).filter(Boolean).join(' ');
const rsiPath=computed(()=>visible.value.map((p,i)=>p.rsi14===null?'':`${x(i)},${90-p.rsi14*.8}`).filter(Boolean).join(' '));
const visibleMarkers=computed(()=>markers.value.map(m=>({...m,index:visible.value.findIndex(p=>p.time===m.time)})).filter(m=>m.index>=0));
const entries=computed(()=>markers.value.filter(m=>m.kind==='entry'));
const fmtTime=(s?:string)=>s?new Date(s).toLocaleString('en-US',{timeZone:'UTC',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',hour12:false})+' UTC':'—';
async function load(before?:number,replace=false){
 if(busy.value)return;busy.value=true;
 try{
  const response=await fetch('/api/chart'+(before?'?before='+before:''),{headers:{Accept:'application/json'}});
  if(!response.ok)throw Error('HTTP '+response.status);
  const data=await response.json();
  if(replace){points.value=data.points;end.value=points.value.length;}
  else if(before){const existing=new Set(points.value.map(p=>p.time));const older=(data.points as Point[]).filter(p=>!existing.has(p.time));points.value=[...older,...points.value];end.value+=older.length;}
  else{const existing=new Map(points.value.map(p=>[p.time,p]));for(const p of data.points as Point[])existing.set(p.time,p);points.value=[...existing.values()].sort((a,b)=>a.time.localeCompare(b.time));end.value=points.value.length;}
  markers.value=data.markers;backtestId.value=data.backtest_id;hasMore.value=data.has_more;updated.value=new Date().toLocaleTimeString();error.value='';
 }catch(e){error.value='Chart request failed: '+String(e);}finally{busy.value=false;}
}
async function older(){if(!hasMore.value||busy.value||!points.value.length)return;await load(Math.floor(new Date(points.value[0].time).getTime()/1000));}
async function pan(delta:number){if(delta<0&&end.value-count.value+delta<15&&hasMore.value)await older();end.value=Math.max(Math.min(points.value.length,end.value+delta),Math.min(count.value,points.value.length));hoverIndex.value=null;}
function wheel(e:WheelEvent){e.preventDefault();if(e.ctrlKey||e.metaKey){zoom(e.deltaY>0?12:-12);}else void pan(e.deltaY>0?-12:12);}
function down(e:PointerEvent){(e.currentTarget as SVGElement).setPointerCapture(e.pointerId);drag.value={x:e.clientX,end:end.value};}
function move(e:PointerEvent){const rect=(e.currentTarget as SVGElement).getBoundingClientRect();if(drag.value){const dx=(e.clientX-drag.value.x)/rect.width*count.value;const next=Math.round(drag.value.end-dx);end.value=Math.max(Math.min(points.value.length,next),Math.min(count.value,points.value.length));return;}const pos=(e.clientX-rect.left)/rect.width*W;const i=Math.floor((pos-LEFT)/(RIGHT-LEFT)*visible.value.length);hoverIndex.value=start.value+Math.max(0,Math.min(visible.value.length-1,i));}
function up(){const nearLeft=end.value-count.value<15;drag.value=null;if(nearLeft&&hasMore.value)void older();}
async function jump(marker:Marker){const at=points.value.findIndex(p=>p.time===marker.time);if(at>=0){end.value=Math.min(points.value.length,at+Math.floor(count.value/2));return;}await load(Math.floor(new Date(marker.time).getTime()/1000)+Math.floor(count.value/2)*3600,true);const index=points.value.findIndex(p=>p.time===marker.time);if(index>=0)end.value=Math.min(points.value.length,index+Math.floor(count.value/2));}
function latestView(){end.value=points.value.length;hoverIndex.value=null;}
function onChartJump(event:Event){const time=(event as CustomEvent<string>).detail;if(time)void jump({time,kind:'entry',price:0,pnl:0});}
onMounted(()=>{if(window.matchMedia('(max-width: 700px)').matches)count.value=36;window.addEventListener('astra:chart-jump',onChartJump);void load(undefined,true);timer=setInterval(()=>{if(!drag.value&&end.value===points.value.length)void load();},60000);});
onUnmounted(()=>{window.removeEventListener('astra:chart-jump',onChartJump);if(timer)clearInterval(timer);});
</script>
<template>
<section class="panel astra-chart">
 <div class="chart-header">
  <div class="chart-heading"><div class="eyebrow">MARKET RESEARCH / BTCUSDT</div><h3>BTC / USDT <span class="chart-timeframe">1H</span></h3><div class="chart-last-price">{{latest?.close.toLocaleString('en-US',{maximumFractionDigits:2})??'—'}} <small>USDT · last completed close</small></div></div>
  <button class="chart-refresh chart-sync" type="button" :disabled="busy" @click="load(undefined,true)" :aria-label="busy?'Refreshing chart':'Refresh chart'"><span aria-hidden="true">↻</span> {{busy?'Loading':'Refresh'}}</button>
 </div>
 <div class="chart-mobile-toolbar" role="group" aria-label="Chart navigation">
  <div class="chart-move-controls"><button type="button" :disabled="busy" @click="older" aria-label="Load older candles" title="Load older candles">⇤ <span>Older</span></button><button type="button" @click="jumpBack" aria-label="Move chart backward" title="Move chart backward">‹</button><button type="button" @click="jumpForward" :disabled="atLatest" aria-label="Move chart forward" title="Move chart forward">›</button><button type="button" @click="latestView" :disabled="atLatest" aria-label="Go to latest candles">Latest ↦</button></div>
  <div class="chart-zoom-controls"><span>Visible candles <b>{{visible.length}}</b></span><div role="group" aria-label="Chart zoom"><button type="button" :disabled="count<=20" @click="zoom(-12)" aria-label="Zoom in">− <span>Zoom in</span></button><button type="button" :disabled="count>=220" @click="zoom(12)" aria-label="Zoom out">+ <span>Zoom out</span></button></div></div>
 </div>
 <p class="chart-gesture-tip">Swipe horizontally to move through candles · Use − / + to zoom</p>
 <p v-if="error" class="warning" role="alert">{{error}}</p>
 <details class="chart-mobile-details" :open="indicatorsOpen" @toggle="indicatorsOpen=($event.target as HTMLDetailsElement).open"><summary>Indicators <span>EMA 20 · EMA 50 · RSI 14</span></summary><div class="chart-legend"><span class="legend-price">Candles</span><span class="legend-ema20">EMA 20</span><span class="legend-ema50">EMA 50</span><span class="legend-breakout">20-bar breakout</span><span class="legend-entry">▲ Entry</span><span class="legend-exit">▼ Exit</span></div></details>
 <details v-if="entries.length" class="chart-mobile-details chart-trade-details" :open="tradesOpen" @toggle="tradesOpen=($event.target as HTMLDetailsElement).open"><summary>Backtest entries <span>{{entries.length}} markers · tap to jump</span></summary><div class="chart-trades"><button v-for="(m,i) in entries.slice(0,150)" :key="i" class="chart-trade" type="button" @click="jump(m)">{{fmtTime(m.time)}} · {{m.pnl>=0?'+':''}}{{m.pnl.toFixed(2)}} USDT</button></div></details>
 <div v-if="visible.length" class="chart-viewport">
  <svg viewBox="0 0 1000 400" role="img" aria-label="Interactive historical BTCUSDT candlestick chart with draggable timeline, zoom and backtest markers" @wheel.prevent="wheel" @pointerdown="down" @pointermove="move" @pointerup="up" @pointercancel="up" @lostpointercapture="up" @pointerleave="hoverIndex=null">
   <g v-for="tick in 5" :key="tick"><line :x1="LEFT" :y1="TOP+(tick-1)*63.75" :x2="RIGHT" :y2="TOP+(tick-1)*63.75" stroke="#2b4148" stroke-dasharray="3 5"/><text x="2" :y="TOP+4+(tick-1)*63.75" fill="#9ab6b5" font-size="11">{{(bounds.max-(tick-1)*(bounds.max-bounds.min)/4).toFixed(0)}}</text></g>
   <g v-for="(p,i) in visible" :key="p.time"><line :x1="x(i)" :x2="x(i)" :y1="y(p.high)" :y2="y(p.low)" :stroke="p.close>=p.open?'#7ad6b4':'#e38d87'"/><rect :x="x(i)-Math.max(1,350/visible.length)" :y="Math.min(y(p.open),y(p.close))" :width="Math.max(2,700/visible.length)" :height="Math.max(1,Math.abs(y(p.open)-y(p.close)))" :fill="p.close>=p.open?'#7ad6b4':'#e38d87'"/><rect :x="x(i)-Math.max(1,350/visible.length)" :y="VOL_TOP+VOL_H-p.volume/volumeMax*VOL_H" :width="Math.max(2,700/visible.length)" :height="p.volume/volumeMax*VOL_H" :fill="p.close>=p.open?'#397c71':'#965b61'"/></g>
   <polyline :points="path('ema20')" fill="none" stroke="#e4c278" stroke-width="1.7"/><polyline :points="path('ema50')" fill="none" stroke="#8eb9f3" stroke-width="1.7"/><polyline :points="path('previous20High')" fill="none" stroke="#bd9ee8" stroke-width="1.2" stroke-dasharray="5 4"/>
   <g v-for="(m,i) in visibleMarkers" :key="i"><path :d="m.kind==='entry'?`M ${x(m.index)} ${y(m.price)+5} l -6 11 h 12 Z`:`M ${x(m.index)} ${y(m.price)-5} l -6 -11 h 12 Z`" :fill="m.kind==='entry'?'#64e0bd':'#f5b17e'"><title>{{m.kind}} · {{m.price.toFixed(2)}} · P&amp;L {{m.pnl.toFixed(2)}} USDT</title></path></g>
   <g v-if="hoverIndex!==null&&hovered"><line :x1="x(hoverIndex-start)" y1="20" :x2="x(hoverIndex-start)" y2="367" stroke="#d6e5e1" stroke-dasharray="4 4" opacity=".8"/><line x1="65" :y1="y(hovered.close)" x2="970" :y2="y(hovered.close)" stroke="#d6e5e1" stroke-dasharray="4 4" opacity=".6"/></g>
   <g v-for="tick in 5" :key="'date'+tick"><text :x="x(Math.min(visible.length-1,Math.floor((tick-1)*(visible.length-1)/4)))" y="391" text-anchor="middle" fill="#91aaa8" font-size="11">{{fmtTime(visible[Math.min(visible.length-1,Math.floor((tick-1)*(visible.length-1)/4))]?.time)}}</text></g>
  </svg>
 </div>
 <p v-else-if="!busy" class="muted">No candles available. Run the historical sync first.</p>
 <div v-if="hovered" class="chart-readout"><span>{{fmtTime(hovered.time)}}</span><span>O {{hovered.open.toFixed(2)}}</span><span>H {{hovered.high.toFixed(2)}}</span><span>L {{hovered.low.toFixed(2)}}</span><span>C {{hovered.close.toFixed(2)}}</span><span>RSI {{hovered.rsi14?.toFixed(1)??'—'}}</span><span>ATR {{hovered.atr14?.toFixed(2)??'—'}}</span></div>
 <div class="chart-rsi"><div class="chart-rsi-head">RSI 14 · {{hovered?.rsi14?.toFixed(1)??'—'}}</div><svg viewBox="0 0 1000 100" role="img" aria-label="RSI 14 indicator"><line x1="65" y1="34" x2="970" y2="34" stroke="#956d6d" stroke-dasharray="4 5"/><line x1="65" y1="66" x2="970" y2="66" stroke="#956d6d" stroke-dasharray="4 5"/><text x="35" y="38" fill="#9ab6b5" font-size="11">70</text><text x="35" y="70" fill="#9ab6b5" font-size="11">30</text><polyline :points="rsiPath" fill="none" stroke="#bba0ee" stroke-width="2"/></svg></div>
 <div class="chart-footer"><span>{{fmtTime(visible[0]?.time)}} — {{fmtTime(visible.at(-1)?.time)}} · {{updated||'waiting'}}</span><span>Backtest {{backtestId?'#'+backtestId:'not run'}} · simulated trades, no execution</span></div>
</section>
</template>
