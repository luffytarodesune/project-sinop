const SINOP_CACHE='sinop-runtime-v157';
const APP_URL=new URL('./index.html',self.registration.scope).href;

self.addEventListener('install',event=>{event.waitUntil(self.skipWaiting())});
self.addEventListener('activate',event=>{event.waitUntil(self.clients.claim())});

function paidActionUrl(data={}){
  const base=data.url||APP_URL;
  const url=new URL(base,self.registration.scope);
  url.searchParams.set('sinopAction','paid');
  if(data.billId)url.searchParams.set('billId',data.billId);
  if(data.month)url.searchParams.set('month',data.month);
  if(data.occurrenceIndex!==null&&data.occurrenceIndex!==undefined)url.searchParams.set('occurrenceIndex',String(data.occurrenceIndex));
  if(data.reminderKey)url.searchParams.set('reminderKey',data.reminderKey);
  return url.href;
}
function supportsNotificationActions(){
  try{return typeof Notification!=='undefined'&&Number(Notification.maxActions||0)>0}catch(_){return false}
}
async function focusOrOpen(data={},action='open'){
  const windows=await self.clients.matchAll({type:'window',includeUncontrolled:true});
  const preferred=windows.find(client=>{
    try{return new URL(client.url).origin===new URL(self.registration.scope).origin}catch(_){return false}
  })||windows[0];
  if(preferred){
    await preferred.focus();
    if(action==='paid')preferred.postMessage({type:'SINOP_NOTIFICATION_PAID',...data});
    return preferred;
  }
  if(self.clients.openWindow){
    const target=action==='paid'?paidActionUrl(data):(data.url||APP_URL);
    return self.clients.openWindow(target);
  }
  return null;
}
self.addEventListener('notificationclick',event=>{
  const notification=event.notification;
  const data=notification.data||{};
  const explicitAction=event.action||'';
  const wantsPaid=explicitAction==='paid'||(!explicitAction&&data.openPaymentOnTap===true);
  notification.close();
  event.waitUntil(focusOrOpen(data,wantsPaid?'paid':'open'));
});
self.addEventListener('push',event=>{
  if(!event.data)return;
  let payload={};
  try{payload=event.data.json()}catch(_){payload={body:event.data.text()}}
  const title=payload.title||'SINOP';
  const data=payload.data||{};
  const paidAction=!!data.paidAction;
  const actionButtons=paidAction&&supportsNotificationActions();
  const baseBody=payload.body||'';
  const body=paidAction&&!actionButtons&&baseBody&&!/tap to mark as paid/i.test(baseBody)?`${baseBody} Tap to mark as paid.`:baseBody;
  const options={
    body,
    tag:payload.tag||data.reminderKey||'sinop-reminder',
    icon:'./sinop-icon-192.png',
    badge:'./sinop-icon-192.png',
    data:{...data,url:data.url||APP_URL,openPaymentOnTap:paidAction||data.openPaymentOnTap===true},
    actions:actionButtons?[{action:'paid',title:'Paid'}]:[]
  };
  event.waitUntil(self.registration.showNotification(title,options));
});
