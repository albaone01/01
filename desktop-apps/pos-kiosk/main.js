const { app, BrowserWindow, Menu, shell } = require('electron');
const path = require('path');

const DEFAULT_URL = 'http://127.0.0.1/ritel4/public/POS/login.php';
const START_URL = process.env.POS_SERVER_URL || DEFAULT_URL;
const ALLOWED_PREFIXES = [
  START_URL.replace(/\/+$/, ''),
  'http://127.0.0.1/ritel4/public/POS',
  'http://localhost/ritel4/public/POS',
  'http://localhost:8000/public/POS'
];

function isAllowedUrl(url) {
  return ALLOWED_PREFIXES.some((prefix) => url.startsWith(prefix));
}

function createWindow() {
  const win = new BrowserWindow({
    width: 1366,
    height: 900,
    show: false,
    autoHideMenuBar: true,
    backgroundColor: '#0f172a',
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true,
      sandbox: true
    }
  });

  Menu.setApplicationMenu(null);
  win.maximize();
  win.setMenuBarVisibility(false);

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

  win.webContents.on('before-input-event', (event, input) => {
    if (input.key === 'F12') event.preventDefault();
    if (input.control && input.shift && ['I', 'J', 'C'].includes(input.key.toUpperCase())) {
      event.preventDefault();
    }
    if (input.control && input.key.toUpperCase() === 'R') {
      event.preventDefault();
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

