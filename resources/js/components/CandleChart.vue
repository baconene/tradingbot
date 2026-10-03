<script setup lang="ts">
import {computed} from 'vue';
export type Candle={open_ms:number;close_ms:number;open:number;high:number;low:number;close:number;volume:number;ema20:number|null;ema50:number|null;ema200:number|null;rsi14:number|null;atr14:number|null;previous_high:number|null;previous_low:number|null};
const props=defineProps<{title:string;subtitle:string;bars:Candle[];selectedMs:number|null}>();
const emit=defineEmits<{select:[ms:number]}>();
const visible=computed(()=>props.bars.slice(-90));
const bounds=computed(()=>{const b=visible.value;return {low:Math.min(...b.map(x=>x.low)),high:Math.max(...b.map(x=>x.high),1)};});
const y=(v:number)=>{const span=Math.max(0.000001,bounds.value.high-bounds.value.low);return 198-(v-bounds.value.low)/span*178;};
const x=(i:number)=>36+i*7;
const line=(key:'ema20'|'ema50'|'previous_high'|'previous_low')=>visible.value.map((b,i)=>b[key]===null?'':x(i)+','+y(b[key] as number)).filter(Boolean).join(' ');
const last=computed(()=>props.bars.length?props.bars[props.bars.length-1]:null);
const format=(v:number|null|undefined)=>v==null?'—':v.toLocaleString(undefined,{maximumFractionDigits:2});
</script>
<template>
<section class="chart-panel">
 <div class="chart-heading"><div><h3>{{title}}</h3><small>{{subtitle}}</small></div><strong v-if="last">{{format(last.close)}}</strong></div>
 <div v-if="!visible.length" class="chart-empty">No completed futures candles imported yet.</div>
 <template v-else>
  <div class="chart-scroll">
   <svg :viewBox="'0 0 '+(Math.max(710,visible.length*7+72))+' 222'" preserveAspectRatio="none" role="img" :aria-label="title+' futures candles with EMA overlays'">
    <line v-for="n in 5" :key="n" x1="30" :x2="Math.max(700,visible.length*7+60)" :y1="n*37" :y2="n*37" stroke="#25364a" stroke-dasharray="3 5"/>
    <g v-for="(b,i) in visible" :key="b.open_ms" class="chart-candle" @click="emit('select',b.open_ms)">
     <line :x1="x(i)" :x2="x(i)" :y1="y(b.high)" :y2="y(b.low)" :stroke="b.close>=b.open?'#4ed7b0':'#e47b82'" stroke-width="1.2"/>
     <rect :x="x(i)-2.5" :y="Math.min(y(b.open),y(b.close))" width="5" :height="Math.max(1,Math.abs(y(b.open)-y(b.close)))" :fill="b.close>=b.open?'#4ed7b0':'#e47b82'"/>
     <line v-if="b.open_ms===selectedMs" :x1="x(i)" :x2="x(i)" y1="10" y2="200" stroke="#c7dcf3" stroke-dasharray="2 4"/>
    </g>
    <polyline v-if="visible.some(b=>b.ema20!==null)" :points="line('ema20')" fill="none" stroke="#edc976" stroke-width="1.2" opacity=".9"/>
    <polyline v-if="visible.some(b=>b.ema50!==null)" :points="line('ema50')" fill="none" stroke="#79a9ff" stroke-width="1.2" opacity=".9"/>
    <text x="3" y="18" fill="#a6b8cb" font-size="10">{{format(bounds.high)}}</text>
    <text x="3" y="206" fill="#a6b8cb" font-size="10">{{format(bounds.low)}}</text>
   </svg>
  </div>
  <div class="chart-indicators"><span><i class="ema20"/>EMA 20 {{format(last?.ema20)}}</span><span><i class="ema50"/>EMA 50 {{format(last?.ema50)}}</span><span>RSI 14 {{format(last?.rsi14)}}</span><span>ATR 14 {{format(last?.atr14)}}</span><span>Prev HH {{format(last?.previous_high)}}</span><span>Prev LL {{format(last?.previous_low)}}</span></div>
 </template>
</section>
</template>