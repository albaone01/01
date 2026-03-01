const { app, BrowserWindow, shell } = require('electron');
const path = require('path');

const DEFAULT_URL = 'http://127.0.0.1/ritel4/public/admin/login.php';
const START_URL = process.env.ADMIN_SERVER_URL || DEFAULT_URL;
const ALLOWED_PREFIXES = [
  START_URL.replace(/\/+$/, ''),
  'http://127.0.0.1/ritel4/public/admin',
  'http://localhost/ritel4/public/admin',
  'http://localhost:8000/public/admin'
];

function isAllowedUrl(url) {
  return ALLOWED_PREFIXES.some((prefix) => url.startsWith(prefix));
}

function createWindow() {
  const win = new BrowserWindow({
    width: 1440,
    height: 900,
    show: false,
    autoHideMenuBar: true,
    backgroundColor: '#ffffff',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: true
    }
  });

  win.webContents.setWindowOpenHandler(({ url }) => {
    if (isAllowedUrl(url)) {
      return { action: 'allow' };
    }
    shell.openExternal(url);
    return { action: 'deny' };
  });

  win.webContents.on('will-navigate', (event, url) => {
    if (!isAllowedUrl(url)) {
      event.preventDefault();
      shell.openExternal(url);
    }
  });

  win.loadURL(START_URL);
  win.once('ready-to-show', () => win.show());
}

app.whenReady().then(() => {
  createWindow();
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') app.quit();
});

