<script setup lang="ts">
import {computed,onMounted,ref} from 'vue';
type Bin={bin:string;count:number;predicted_rate:number;observed_rate:number};
type Run={id:number;version:string;status:string;created_at:string;metrics:{source_backtest_id:number;training_trades:number;holdout_trades:number;train_win_rate:number;holdout_win_rate:number;brier_score:number;baseline_brier_score:number;calibration_bins:Bin[];limitations:string[]}};
const runs=ref<Run[]>([]),loading=ref(false),error=ref(''),selected=ref<number|null>(null);
const current=computed(()=>runs.value.find(r=>r.id===selected.value)??runs.value[0]);
const percent=(n:number|undefined)=>n==null?'—':(n*100).toFixed(1)+'%';
async function refresh(){loading.value=true;try{const r=await fetch('/api/research/predictions',{cache:'no-store'});if(!r.ok)throw Error('HTTP '+r.status);const data=await r.json();runs.value=data.runs??[];error.value='';}catch(e){error.value=String(e);}finally{loading.value=false;}}
onMounted(()=>void refresh());
</script>
<template>
<section class="panel prediction-lab">
 <div class="panelhead"><div><div class="eyebrow">MILESTONE 5 / OFFLINE MODEL RESEARCH</div><h3>Prediction lab</h3><p class="chart-note">Historical, observation-only probability estimates for profitable closed trades</p></div><button class="chart-refresh" type="button" :disabled="loading" @click="refresh">{{loading?'Loading…':'Refresh'}}</button></div>
 <p class="risk-banner">No model is approved. Predictions cannot place orders, override risk limits or change the deployed breakout strategy.</p>
 <p v-if="error" class="warning" role="alert">{{error}}</p>
 <div v-if="!runs.length" class="backtest-empty">No trained research model yet. On Forge, run <code>php artisan astra:backtest</code>, then <code>php artisan astra:train-prediction</code>. At least 60 completed trades with valid entry indicators are required.</div>
 <template v-else>
 <div class="training-actions"><label for="model-run">Saved experiment</label><select id="model-run" v-model.number="selected" class="training-token"><option :value="null">Latest</option><option v-for="r in runs" :key="r.id" :value="r.id">#{{r.id}} · {{r.created_at}}</option></select></div>
 <div v-if="current" class="training-grid">
  <div class="training-stat"><small>Chronological training trades</small><strong>{{current.metrics.training_trades}}</strong><small>Training win rate {{percent(current.metrics.train_win_rate)}}</small></div>
  <div class="training-stat"><small>Unseen holdout trades</small><strong>{{current.metrics.holdout_trades}}</strong><small>Holdout win rate {{percent(current.metrics.holdout_win_rate)}}</small></div>
  <div class="training-stat"><small>Holdout Brier score</small><strong>{{current.metrics.brier_score.toFixed(4)}}</strong><small>Training-prior baseline: {{current.metrics.baseline_brier_score.toFixed(4)}} · lower is better</small></div>
 </div>
 <h3>Holdout calibration</h3><p class="training-note">Predicted versus observed profitable-trade frequency in occupied probability bins. Sparse bins are not reliable evidence of calibration.</p>
 <div v-if="current" class="prediction-bins"><div v-for="bin in current.metrics.calibration_bins" :key="bin.bin" class="prediction-bin"><div class="training-run-head"><strong>Predicted {{percent(bin.predicted_rate)}}</strong><small>{{bin.count}} holdout trades</small></div><div class="prediction-track"><div :style="{width:Math.min(100,bin.predicted_rate*100)+'%'}"></div></div><div class="prediction-track observed"><div :style="{width:Math.min(100,bin.observed_rate*100)+'%'}"></div></div><small>Observed: {{percent(bin.observed_rate)}}</small></div></div>
 <p class="training-note">Mint: predicted probability · Purple: observed outcome rate</p>
 <details v-if="current" class="prediction-details"><summary>Method and limitations</summary><p>Model: smoothed RSI / relative-volume bins; 75% chronological training and 25% holdout. Label: positive net P&amp;L after simulated costs. Source backtest #{{current.metrics.source_backtest_id}}.</p><ul><li v-for="item in current.metrics.limitations" :key="item">{{item}}</li></ul></details>
 </template>
</section>
</template>
