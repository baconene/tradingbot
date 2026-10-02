<script setup lang="ts">
import {onMounted,onUnmounted,reactive,ref} from 'vue';
type Risk={execution_enabled:boolean;kill_latched:boolean;reconciled:boolean;testnet_submission_certified:boolean;risk_limits:Record<string,number>};
const state=ref<Risk|null>(null),error=ref(''),loading=ref(false),result=ref<{decision:{allowed:boolean;reason:string;notional?:number;risk_usdt?:number}}|null>(null);
const form=reactive({equity:1000,daily_reference:1000,high_water:1000,daily_pnl:0,open_positions:0,exposure:0,entry:100,stop:98,quantity:1,min_notional:5,step_size:0.01,min_qty:0.01,manual_approved:false});
let timer:ReturnType<typeof setInterval>|undefined;
async function refresh(){try{const r=await fetch('/api/risk/status',{cache:'no-store'});if(!r.ok)throw Error('Risk status unavailable');state.value=await r.json();error.value='';}catch(e){error.value=String(e);}}
async function evaluate(){loading.value=true;result.value=null;try{const r=await fetch('/api/risk/shadow-evaluate',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1]??'')},body:JSON.stringify(form)});const data=await r.json();if(!r.ok)throw Error(data.message??'Invalid scenario');result.value=data;error.value='';}catch(e){error.value=String(e);}finally{loading.value=false;}}
onMounted(()=>{void refresh();timer=setInterval(()=>{if(!document.hidden)void refresh();},15000);});onUnmounted(()=>{if(timer)clearInterval(timer);});
</script>
<template>
<section class="risk-layout">
 <article class="panel"><div class="panelhead"><div><div class="eyebrow">MILESTONE 4</div><h3>Execution safety</h3></div><button type="button" class="chart-refresh" @click="refresh">Refresh</button></div>
 <p class="risk-banner">Order submission and cancellation remain hard-locked. This page cannot unlock the exchange or change the kill switch.</p>
 <div class="risk-audit"><div><span>Execution</span><strong class="locked">DISABLED</strong></div><div><span>Persistent kill switch</span><strong :class="state?.kill_latched?'locked':'warning'">{{state?(state.kill_latched?'LATCHED':'UNLATCHED'):'UNKNOWN'}}</strong></div><div><span>Portfolio reconciled</span><strong>{{state?.reconciled?'Yes':'No / unknown'}}</strong></div><div><span>Testnet certified</span><strong>{{state?.testnet_submission_certified?'Yes':'No'}}</strong></div></div>
 <h3 style="margin-top:24px">Risk limits</h3><div v-if="state" class="risk-audit"><div v-for="(v,k) in state.risk_limits" :key="k"><span>{{String(k).replaceAll('_',' ')}}</span><strong>{{v}}</strong></div></div>
 <p class="training-note">Read-only server status. Exchange connectivity, protective stops, partial-fill reconciliation and kill-switch drills remain certification gates.</p>
 </article>
 <article class="panel"><div class="panelhead"><div><div class="eyebrow">SCENARIO LAB</div><h3>Hypothetical order risk</h3></div><span class="warning">NO ORDERS</span></div>
 <p class="training-note">Explore deterministic risk limits with hypothetical values. The scenario assumes reconciliation and an unlatched switch only inside the calculation; actual execution stays locked. Exchange filters are supplied by you, not verified.</p>
 <form class="risk-eval-form" @submit.prevent="evaluate">
  <label v-for="key in ['equity','daily_reference','high_water','daily_pnl','open_positions','exposure','entry','stop','quantity','min_notional','step_size','min_qty'] as (keyof typeof form)[]" :key="key">{{key.replaceAll('_',' ')}}<input v-model.number="form[key]" type="number" step="any" required/></label>
  <label style="display:flex;align-items:center;gap:8px;grid-column:1/-1"><input v-model="form.manual_approved" type="checkbox" style="width:auto"/> Hypothetical manual approval</label>
  <button type="submit" class="chart-refresh" :disabled="loading">{{loading?'Evaluating…':'Evaluate scenario'}}</button>
 </form>
 <p v-if="error" class="warning" role="alert">{{error}}</p>
 <div v-if="result" class="risk-banner" role="status"><strong>{{result.decision.allowed?'Passes hypothetical risk limits':'Blocked by risk policy'}}</strong><p>Reason: {{result.decision.reason}}</p><p v-if="result.decision.notional">Notional: {{result.decision.notional.toFixed(2)}} USDT · Stop risk: {{result.decision.risk_usdt?.toFixed(2)}} USDT</p><small>Not permission to trade. Fees, spread, slippage and actual exchange filters are not evaluated.</small></div>
 </article>
</section>
</template>
