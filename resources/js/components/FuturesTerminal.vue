<script setup lang="ts">
import {onMounted,onUnmounted,ref,computed} from 'vue';
type Parameters={structure_lookback:number;reward_risk:number;leverage:number;risk_per_trade_pct:number;daily_loss_limit_pct:number;drawdown_limit_pct:number;max_position_notional_pct:number;min_model_probability:number;require_regime_confirmation:boolean;allow_short:boolean};
type Config={id:number;version:string;name:string;status:string;parameters:Parameters;created_at:string};
const defaults:Parameters={structure_lookback:20,reward_risk:2,leverage:2,risk_per_trade_pct:.25,daily_loss_limit_pct:2,drawdown_limit_pct:10,max_position_notional_pct:25,min_model_probability:.65,require_regime_confirmation:true,allow_short:false};
const form=ref<Parameters>({...defaults}),name=ref('BTC structure research'),configs=ref<Config[]>([]);
const ready=ref({futures_data:false,exchange_execution:false,protective_orders:false,independent_model_validation:false,testnet_certified:false});
const latest=ref<string|null>(null),risk=ref({reconciled:false,kill_latched:true,last_observed_at:null as string|null});
const busy=ref(false),saving=ref(false),error=ref(''),notice=ref('');let timer:ReturnType<typeof setInterval>|undefined;
const marginPct=computed(()=>100/form.value.leverage);
async function refresh(){if(busy.value)return;busy.value=true;try{const r=await fetch('/api/futures/terminal',{cache:'no-store'});if(!r.ok)throw Error('HTTP '+r.status);const d=await r.json();configs.value=d.configs;ready.value=d.readiness;latest.value=d.latest_futures_candle;risk.value=d.risk;error.value='';}catch(e){error.value=String(e);}finally{busy.value=false;}}
async function save(){if(saving.value)return;saving.value=true;notice.value='';try{
const token=document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content??'';
const r=await fetch('/api/futures/configs',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token},body:JSON.stringify({name:name.value,parameters:form.value})});
const d=await r.json();if(!r.ok)throw Error(d.message??'Could not save draft');notice.value='Immutable research draft saved. Trading remains locked.';await refresh();
}catch(e){error.value=String(e);}finally{saving.value=false;}}
function useConfig(c:Config){form.value={...c.parameters};name.value=c.name+' (new draft)';notice.value='Loaded as editable copy. Saving creates a new version.';}
onMounted(()=>{void refresh();timer=setInterval(()=>{if(!document.hidden)void refresh();},30000);});
onUnmounted(()=>{if(timer)clearInterval(timer);});
</script>
<template>
<section class="terminal-grid">
 <div class="panel terminal-main">
  <div class="panelhead"><div><div class="eyebrow">ASTRA FUTURES V2 / STAGED IMPLEMENTATION</div><h3>Futures terminal</h3><p class="chart-note">Independent futures infrastructure. Existing chart remains spot research until a verified futures feed is connected.</p></div><span class="locked">EXECUTION LOCKED</span></div>
  <div class="terminal-status"><div><small>Futures data</small><strong>{{ready.futures_data?'Imported':'Not connected'}}</strong><span>{{latest??'No verified futures candles'}}</span></div><div><small>Exchange reconciliation</small><strong>{{risk.reconciled?'Recorded':'Unverified'}}</strong><span>Kill latch {{risk.kill_latched?'ON':'OFF'}}</span></div><div><small>Protective orders</small><strong>{{ready.protective_orders?'Verified':'Not certified'}}</strong><span>No live order entry</span></div></div>
  <p class="risk-banner">This terminal is a research workspace, not a live futures account. 100× is a scenario parameter, not an exchange order setting.</p>
  <div class="terminal-panel-title"><h3>Versioned strategy configuration</h3><small>New saves are immutable drafts</small></div>
  <div class="terminal-form">
   <label class="terminal-full">Experiment name<input v-model="name" maxlength="100" placeholder="BTC structure research"/></label>
   <label>Leverage <strong>{{form.leverage}}×</strong><input v-model.number="form.leverage" type="range" min="1" max="100" step="1"/><span>1×–100× research only</span></label>
   <label>Reward : risk <strong>{{form.reward_risk}} : 1</strong><input v-model.number="form.reward_risk" type="range" min="0.5" max="10" step="0.5"/></label>
   <label>Structure lookback<input v-model.number="form.structure_lookback" type="number" min="5" max="100"/></label>
   <label>Minimum calibrated probability<input v-model.number="form.min_model_probability" type="number" min="0.5" max="0.95" step="0.01"/></label>
   <label>Stop risk / equity (%)<input v-model.number="form.risk_per_trade_pct" type="number" min="0.01" max="1" step="0.05"/></label>
   <label>Daily loss ceiling (%)<input v-model.number="form.daily_loss_limit_pct" type="number" min="0.1" max="5" step="0.1"/></label>
   <label>Drawdown ceiling (%)<input v-model.number="form.drawdown_limit_pct" type="number" min="0.1" max="20" step="0.5"/></label>
   <label>Max position notional / equity (%)<input v-model.number="form.max_position_notional_pct" type="number" min="1" max="50" step="1"/></label>
   <label class="terminal-toggle"><input v-model="form.require_regime_confirmation" type="checkbox"/>Require market-regime confirmation</label>
   <label class="terminal-toggle"><input v-model="form.allow_short" type="checkbox"/>Include short research</label>
  </div>
  <div class="terminal-margin">At {{form.leverage}}×, initial margin is approximately {{marginPct.toFixed(2)}}% of notional. Liquidation may occur before that adverse price move. Leverage never increases the permitted stop-risk budget.</div>
  <p v-if="error" class="warning" role="alert">{{error}}</p><p v-if="notice" class="profit" role="status">{{notice}}</p>
  <button class="chart-refresh terminal-save" type="button" :disabled="saving||!name.trim()" @click="save">{{saving?'Saving…':'Save new research draft'}}</button>
 </div>
 <aside class="panel terminal-side">
  <div class="panelhead"><h3>Research versions</h3><button class="chart-refresh" :disabled="busy" @click="refresh">Refresh</button></div>
  <p v-if="!configs.length" class="training-note">No futures research versions yet. Save a draft to start tracking configurations.</p>
  <div v-for="c in configs" :key="c.id" class="terminal-version"><div><strong>{{c.name}}</strong><small>{{c.status.toUpperCase()}} · {{c.created_at}}</small><small>{{c.parameters.leverage}}× · {{c.parameters.reward_risk}}:1 · {{c.parameters.risk_per_trade_pct}}% risk</small></div><button type="button" @click="useConfig(c)">Copy</button></div>
  <h3>Release gates</h3><div v-for="(ok,key) in ready" :key="key" class="status"><span>{{String(key).replaceAll('_',' ')}}</span><strong :class="ok?'profit':'warning'">{{ok?'Recorded':'Pending'}}</strong></div>
 </aside>
</section>
</template>
