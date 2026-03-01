const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('kioskMeta', {
  app: 'Ritel4 POS Kiosk',
  version: '1.0.0'
});

