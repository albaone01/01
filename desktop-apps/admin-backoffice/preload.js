const { contextBridge } = require('electron');

contextBridge.exposeInMainWorld('adminMeta', {
  app: 'Ritel4 Admin Backoffice',
  version: '1.0.0'
});

