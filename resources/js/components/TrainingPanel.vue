<script setup lang="ts">
import {computed,onMounted,onUnmounted,ref} from 'vue';
type Summary={trade_count?:number;trades?:number;return_pct?:number;realized_return_pct?:number;win_rate_pct?:number|null;max_drawdown_pct?:number;profit_factor?:number|null};
type Fold={fold:number;test_start:string;test_end:string;selection_eligible:boolean;selected_parameters:Record<string,number>;test:Summary;baseline:Summary};
type Report={id:number;kind:string;created_at:string;data_start:string;data_end:string;results:{mode:string;candidate_count?:number;selection_eligible?:boolean;selected_parameters?:Record<string,number>;training?:Summary;holdout?:Summary;holdout_baseline?:Summary;folds?:Fold[];candidates?:unknown[]}};
const token=ref(''),reports=ref<Report[]>([]),status=ref<{state:string;step?:string;message?:string;step_index?:number;step_total?:number;step_completed?:boolean;updated_at?:string;started_at?:string;finished_at?:string}>({state:'idle'}),busy=ref(false),error=ref(''),notice=ref('');
const lastSync=ref(''),lastResearchId=ref<number|null>(null),changed=ref(false),network=ref<'connected'|'offline'|'checking'>('checking');
let poll:ReturnType<typeof setInterval>|undefined;
const pct=(n:number|null|undefined)=>n==null?'—':n.toFixed(2)+'%';
const latestWalk=computed(()=>reports.value.find(r=>r.kind.includes('walk-forward')));
const folds=computed(()=>latestWalk.value?.results.folds??[]);
const chart=computed(()=>{
 const rows=folds.value.map(f=>({fold:f.fold,candidate:f.test.return_pct??null,baseline:f.baseline.return_pct??null}));
 const values=rows.flatMap(r=>[r.candidate,r.baseline].filter((n):n is number=>n!==null));
 const lo=Math.min(0,...values),hi=Math.max(0,...values),range=Math.max(1,hi-lo);
 const x=(i:number)=>55+i*565/Math.max(1,rows.length-1),y=(n:number)=>180-(n-lo)/range*145;
 return {zero:y(0),top:hi,bottom:lo,rows:rows.map((r,i)=>({...r,x:x(i),candidateY:r.candidate===null?null:y(r.candidate),baselineY:r.baseline===null?null:y(r.baseline)})),
 candidate:rows.filter(r=>r.candidate!==null).map((r,i)=>x(i)+','+y(r.candidate!)).join(' '),
 baseline:rows.filter(r=>r.baseline!==null).map((r,i)=>x(i)+','+y(r.baseline!)).join(' ')};
});
const stageLabel=computed(()=>({'astra:backtest':'Baseline backtest','astra:optimize':'18-candidate optimization','astra:walk-forward':'Four-fold walk-forward'} as Record<string,string>)[status.value.step??'']??status.value.state);
const stale=computed(()=>['queued','running'].includes(status.value.state)&&!!status.value.updated_at&&Date.now()-new Date(status.value.updated_at).getTime()>35*60*1000);
const updated=computed(()=>status.value.updated_at?new Date(status.value.updated_at).toLocaleString():'No active research job');
async function load(){
 try{const response=await fetch('/api/research/training?ts='+Date.now(),{cache:'no-store'});if(!response.ok)throw Error('HTTP '+response.status);
 const data=await response.json();
 if(lastResearchId.value!==null&&data.latest_research_id!==lastResearchId.value){changed.value=true;notice.value='A new research report is available.';}
 lastResearchId.value=data.latest_research_id??null;reports.value=data.runs??[];status.value=data.training??{state:'idle'};
 lastSync.value=new Date().toLocaleTimeString();network.value='connected';error.value='';
 }catch(e){network.value='offline';error.value='Refresh failed: '+String(e);}
}
async function retrain(){
 if(!token.value.trim()){error.value='Enter your operator training token (configured on Forge).';return;}
 if(!confirm('Queue baseline backtest, 18-candidate optimization and four-fold walk-forward research? No live trading will occur.'))return;
 busy.value=true;error.value='';notice.value='';changed.value=false;
 try{const response=await fetch('/api/research/training',{method:'POST',headers:{'Authorization':'Bearer '+token.value.trim(),'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1]??'')},body:'{}'});
 const data=await response.json();if(!response.ok)throw Error(data.message??'HTTP '+response.status);
 notice.value='Research queued. Waiting for the Forge research queue worker.';token.value='';await load();
 }catch(e){error.value=String(e);}finally{busy.value=false;}
}
function onFocus(){void load();}
onMounted(()=>{void load();poll=setInterval(()=>{if(!document.hidden)void load();},5000);window.addEventListener('focus',onFocus);document.addEventListener('visibilitychange',onFocus);});
onUnmounted(()=>{if(poll)clearInterval(poll);window.removeEventListener('focus',onFocus);document.removeEventListener('visibilitychange',onFocus);});
</script>
<template>
<section class="panel training-panel">
 <div class="panelhead"><div><div class="eyebrow">MILESTONE 3 / RESEARCH LAB</div><h3>Training analysis</h3><p class="chart-note">Historical parameter experiments, not a machine-learning model or live trading</p></div><button class="chart-refresh" type="button" @click="load">Refresh</button></div>
 <div class="training-actions"><input v-model="token" class="training-token" type="password" autocomplete="off" placeholder="Operator training token" aria-label="Operator training token"/><button class="chart-refresh" type="button" :disabled="busy||status.state==='queued'||status.state==='running'" @click="retrain">{{busy?'Submitting…':'Retrain research ↗'}}</button><span class="training-note">Job: {{status.state}} · API: {{network}} · Last checked: {{lastSync||'checking…'}}</span></div>
 <p class="training-note">The page refreshes every 5 seconds while visible. New data appears when the worker saves a report; this is not a streaming AI model. Research never changes the trading strategy automatically.</p>
 <p v-if="notice" class="profit">{{notice}}</p><p v-if="error||status.message" class="warning">{{error||status.message}}</p>
 <p v-if="stale" class="warning">No job-status update for over 35 minutes. Check the Forge research queue worker and failed jobs.</p>
 <div class="training-grid"><div class="training-stat"><small>Saved experiments (latest 20)</small><strong>{{reports.length}}</strong></div><div class="training-stat"><small>Latest walk-forward folds</small><strong>{{folds.length||'—'}}</strong></div><div class="training-stat"><small>Worker stage</small><strong>{{stageLabel}}</strong><small v-if="status.step_total">Stage {{status.step_index}} of {{status.step_total}}{{status.step_completed?' · completed':''}}</small></div></div>
 <div v-if="status.state==='queued'||status.state==='running'" class="training-progress"><strong>{{status.state==='queued'?'Waiting for queue worker':stageLabel}}</strong><div class="training-progress-track"><div :style="{width:(((status.step_index??0)-(status.step_completed?0:1))/3*100)+'%'}"></div></div><small>Completed stages: {{Math.max(0,(status.step_index??0)-(status.step_completed?0:1))}} / 3 · Last update: {{updated}}. A stage can take several minutes without intermediate updates.</small></div>
 <h3>Latest walk-forward test curve</h3><p class="training-note">Four chronological test folds from the latest saved walk-forward run. Each point is the return for that fold, not cumulative profit, training loss or a continuously learning model.</p>
 <svg v-if="chart.rows.length" class="training-curve" viewBox="0 0 680 225" role="img" aria-label="Candidate and baseline returns by chronological walk-forward test fold">
  <line x1="55" :y1="chart.zero" x2="620" :y2="chart.zero" stroke="#667c83" stroke-dasharray="4 4"/>
  <text x="6" :y="chart.zero-5" fill="#9db9b4" font-size="11">0%</text>
  <polyline :points="chart.baseline" fill="none" stroke="#a6a0c6" stroke-width="2" stroke-dasharray="5 4"/>
  <polyline :points="chart.candidate" fill="none" stroke="#7ad6b4" stroke-width="2.5"/>
  <g v-for="p in chart.rows" :key="p.fold"><circle v-if="p.candidateY!==null" :cx="p.x" :cy="p.candidateY" r="5" fill="#7ad6b4"><title>Fold {{p.fold}} candidate: {{pct(p.candidate)}}</title></circle><circle v-if="p.baselineY!==null" :cx="p.x" :cy="p.baselineY" r="4" fill="#a6a0c6"><title>Fold {{p.fold}} baseline: {{pct(p.baseline)}}</title></circle><text :x="p.x" y="210" text-anchor="middle" fill="#9db9b4" font-size="11">Fold {{p.fold}}</text></g>
 </svg><p v-else class="backtest-empty">No saved walk-forward results. Run research to generate a chronological test curve.</p>
 <p class="training-note"><span class="profit">━━ Candidate</span> &nbsp; <span style="color:#a6a0c6">┄ Baseline</span> · {{latestWalk?'Run #'+latestWalk.id:'No run'}} <span v-if="changed" class="profit">· New research report received</span></p>
 <h3>Experiment records</h3>
 <div class="training-history"><article v-for="r in reports" :key="r.id" class="training-run">
  <div class="training-run-head"><div><strong>{{r.kind.includes('walk-forward')?'Walk-forward validation':'Parameter optimization'}} #{{r.id}}</strong><br/><small>{{r.created_at}} · {{r.data_start}} → {{r.data_end}} UTC</small></div><small>RESEARCH ONLY · NOT PROMOTED</small></div>
  <template v-if="r.results.folds"><div class="training-folds"><div v-for="f in r.results.folds" :key="f.fold"><strong>Fold {{f.fold}}</strong><br/><small>{{f.test_start}} → {{f.test_end}}</small><p>Candidate: <span :class="(f.test.return_pct??0)>=0?'profit':'loss'">{{pct(f.test.return_pct)}}</span></p><p>Baseline: {{pct(f.baseline.return_pct)}}</p><small>{{f.selection_eligible?'Training eligible':'No eligible training candidate'}}</small></div></div></template>
  <template v-else><div class="training-folds"><div><small>Test return</small><p :class="(r.results.holdout?.realized_return_pct??0)>=0?'profit':'loss'">{{pct(r.results.holdout?.realized_return_pct)}}</p></div><div><small>Baseline return</small><p>{{pct(r.results.holdout_baseline?.realized_return_pct)}}</p></div><div><small>Training candidates</small><p>{{r.results.candidate_count??'—'}}</p><small>{{r.results.selection_eligible?'Selected candidate eligible':'No eligible candidate'}}</small></div></div></template>
 </article></div>
</section>
</template>
