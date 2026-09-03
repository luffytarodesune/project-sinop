const SINOP_CACHE='sinop-shell-v130';
const CORE=['./sinop-manifest.webmanifest','./sinop-icon-192.png','./sinop-icon-512.png','./sinop-icon-180.png'];
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(SINOP_CACHE).then(c=>c.addAll(CORE).catch(()=>{})).then(()=>self.skipWaiting()));
});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(k=>k.startsWith('sinop-shell-')&&k!==SINOP_CACHE).map(k=>caches.delete(k)))).then(()=>self.clients.claim()));
});
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return;
  event.respondWith(fetch(event.request).catch(()=>caches.match(event.request)));
});
self.addEventListener('push',event=>{
  let data={};
  try{data=event.data?event.data.json():{}}catch(_){data={body:event.data?.text?.()||'You have a new SINOP reminder.'}}
  const title=data.title||'SINOP';
  const options={
    body:data.body||data.message||'You have a new SINOP reminder.',
    icon:'./sinop-icon-192.png',badge:'./sinop-icon-192.png',
    tag:data.tag||'sinop-push',renotify:false,
    data:{url:data.url||'./',...(data.data||{})}
  };
  event.waitUntil(self.registration.showNotification(title,options));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();
  const target=event.notification.data?.url||'./';
  event.waitUntil(self.clients.matchAll({type:'window',includeUncontrolled:true}).then(clients=>{
    for(const client of clients){
      if('focus' in client){client.navigate?.(target).catch(()=>{});return client.focus();}
    }
    return self.clients.openWindow?self.clients.openWindow(target):undefined;
  }));
});
