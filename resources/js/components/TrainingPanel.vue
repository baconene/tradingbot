<script setup lang="ts">
import {computed,onMounted,onUnmounted,ref} from 'vue';
type Summary={trade_count?:number;trades?:number;return_pct?:number;realized_return_pct?:number;win_rate_pct?:number|null;max_drawdown_pct?:number;profit_factor?:number|null};
type Fold={fold:number;test_start:string;test_end:string;selection_eligible:boolean;selected_parameters:Record<string,number>;test:Summary;baseline:Summary};
type Report={id:number;kind:string;created_at:string;data_start:string;data_end:string;results:{mode:string;candidate_count?:number;selection_eligible?:boolean;selected_parameters?:Record<string,number>;training?:Summary;holdout?:Summary;holdout_baseline?:Summary;folds?:Fold[];candidates?:unknown[]}};
const token=ref(''),reports=ref<Report[]>([]),status=ref<{state:string;step?:string;message?:string}>({state:'idle'}),busy=ref(false),error=ref(''),notice=ref('');
let poll:ReturnType<typeof setInterval>|undefined;
const pct=(n:number|null|undefined)=>n==null?'—':n.toFixed(2)+'%';
const ordered=computed(()=>[...reports.value].reverse());
const points=computed(()=>ordered.value.map((r,i)=>{
 const v=r.kind.includes('walk-forward')?r.results.folds?.reduce((s,f)=>s+(f.test.return_pct??0),0):r.results.holdout?.realized_return_pct;
 const base=r.kind.includes('walk-forward')?r.results.folds?.reduce((s,f)=>s+(f.baseline.return_pct??0),0):r.results.holdout_baseline?.realized_return_pct;
 return {id:r.id,x:i,y:v??null,baseline:base??null,kind:r.kind};
}).filter(p=>p.y!==null));
const chart=computed(()=>{
 const all=points.value.flatMap(p=>[p.y!,p.baseline].filter((v):v is number=>v!==null));
 const lo=Math.min(0,...all),hi=Math.max(0,...all),range=Math.max(1,hi-lo);
 const x=(i:number)=>45+i*590/Math.max(1,points.value.length-1);
 const y=(v:number)=>190-(v-lo)/range*155;
 return {lo,hi,zero:y(0),candidate:points.value.map((p,i)=>x(i)+','+y(p.y!)).join(' '),
 baseline:points.value.filter(p=>p.baseline!==null).map(p=>x(p.x)+','+y(p.baseline!)).join(' '),
 dots:points.value.map((p,i)=>({x:x(i),y:y(p.y!),id:p.id}))};
});
async function load(){
 try{const response=await fetch('/api/research/training',{cache:'no-store'});if(!response.ok)throw Error('HTTP '+response.status);
 const data=await response.json();reports.value=data.runs??[];status.value=data.training??{state:'idle'};error.value='';
 }catch(e){error.value=String(e);}
}
async function retrain(){
 if(!token.value.trim()){error.value='Enter your operator training token (configured on Forge).';return;}
 if(!confirm('Queue baseline backtest, 18-candidate optimization and four-fold walk-forward research? No live trading will occur.'))return;
 busy.value=true;error.value='';notice.value='';
 try{const response=await fetch('/api/research/training',{method:'POST',headers:{'Authorization':'Bearer '+token.value.trim(),'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1]??'')},body:'{}'});
 const data=await response.json();if(!response.ok)throw Error(data.message??'HTTP '+response.status);
 notice.value='Research queued. A Forge research queue worker must be running.';token.value='';await load();
 }catch(e){error.value=String(e);}finally{busy.value=false;}
}
onMounted(()=>{void load();poll=setInterval(()=>void load(),15000);});
onUnmounted(()=>{if(poll)clearInterval(poll);});
</script>
<template>
<section class="panel training-panel">
 <div class="panelhead"><div><div class="eyebrow">MILESTONE 3 / RESEARCH LAB</div><h3>Training analysis</h3><p class="chart-note">Historical parameter experiments, not a machine-learning model or live trading</p></div><button class="chart-refresh" type="button" @click="load">Refresh</button></div>
 <div class="training-actions"><input v-model="token" class="training-token" type="password" autocomplete="off" placeholder="Operator training token" aria-label="Operator training token"/><button class="chart-refresh" type="button" :disabled="busy||status.state==='queued'||status.state==='running'" @click="retrain">{{busy?'Submitting…':'Retrain research ↗'}}</button><span class="training-note">Status: {{status.state}} {{status.step?'· '+status.step:''}}</span></div>
 <p class="training-note">Runs the baseline backtest, bounded optimization and walk-forward validation in a background research queue. No parameter is automatically promoted.</p>
 <p v-if="notice" class="profit">{{notice}}</p><p v-if="error||status.message" class="warning">{{error||status.message}}</p>
 <div class="training-grid"><div class="training-stat"><small>Saved experiments</small><strong>{{reports.length}}</strong></div><div class="training-stat"><small>Walk-forward rounds in latest run</small><strong>{{reports.find(r=>r.kind.includes('walk-forward'))?.results.folds?.length??'—'}}</strong></div><div class="training-stat"><small>Research state</small><strong>{{status.state}}</strong></div></div>
 <h3>Research performance curve</h3><p class="training-note">Test-period return per saved experiment (walk-forward: sum of sequential fold returns, not compounded). Baselines use the same periods. Different historical windows are not directly comparable; this is experiment history, not a learning or account-equity curve.</p>
 <svg v-if="points.length" class="training-curve" viewBox="0 0 680 225" role="img" aria-label="Research candidate and baseline return across saved experiments">
  <line x1="45" :y1="chart.zero" x2="635" :y2="chart.zero" stroke="#667c83" stroke-dasharray="4 4"/>
  <text x="5" :y="chart.zero-5" fill="#9db9b4" font-size="10">0%</text>
  <polyline v-if="points.length>1" :points="chart.baseline" fill="none" stroke="#a6a0c6" stroke-width="2" stroke-dasharray="5 4"/>
  <polyline v-if="points.length>1" :points="chart.candidate" fill="none" stroke="#7ad6b4" stroke-width="2.5"/>
  <circle v-for="p in chart.dots" :key="p.id" :cx="p.x" :cy="p.y" r="4" fill="#7ad6b4"><title>Run #{{p.id}}</title></circle>
  <text x="45" y="214" fill="#9db9b4" font-size="11">Earlier runs</text><text x="635" y="214" text-anchor="end" fill="#9db9b4" font-size="11">Latest</text>
 </svg><p v-else class="backtest-empty">No saved research experiments yet. Historical reports will appear after optimization or walk-forward testing.</p>
 <p class="training-note"><span class="profit">━━ Candidate</span> &nbsp; <span style="color:#a6a0c6">┄ Baseline</span></p>
 <h3>Experiment records</h3>
 <div class="training-history"><article v-for="r in reports" :key="r.id" class="training-run">
  <div class="training-run-head"><div><strong>{{r.kind.includes('walk-forward')?'Walk-forward validation':'Parameter optimization'}} #{{r.id}}</strong><br/><small>{{r.created_at}} · {{r.data_start}} → {{r.data_end}} UTC</small></div><small>RESEARCH ONLY · NOT PROMOTED</small></div>
  <template v-if="r.results.folds"><div class="training-folds"><div v-for="f in r.results.folds" :key="f.fold"><strong>Fold {{f.fold}}</strong><br/><small>{{f.test_start}} → {{f.test_end}}</small><p>Candidate: <span :class="(f.test.return_pct??0)>=0?'profit':'loss'">{{pct(f.test.return_pct)}}</span></p><p>Baseline: {{pct(f.baseline.return_pct)}}</p><small>{{f.selection_eligible?'Training eligible':'No eligible training candidate'}}</small></div></div></template>
  <template v-else><div class="training-folds"><div><small>Test return</small><p :class="(r.results.holdout?.realized_return_pct??0)>=0?'profit':'loss'">{{pct(r.results.holdout?.realized_return_pct)}}</p></div><div><small>Baseline return</small><p>{{pct(r.results.holdout_baseline?.realized_return_pct)}}</p></div><div><small>Training candidates</small><p>{{r.results.candidate_count??'—'}}</p><small>{{r.results.selection_eligible?'Selected candidate eligible':'No eligible candidate'}}</small></div></div></template>
 </article></div>
</section>
</template>
