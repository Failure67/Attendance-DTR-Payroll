const { app, BrowserWindow, ipcMain } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

let phpServer = null;
let mainWindow = null;
const isDev = process.env.NODE_ENV === 'development' || !app.isPackaged;

function getPhpPath() {
  if (isDev) {
    return 'php';
  } else {
    return path.join(process.resourcesPath, 'php', 'php.exe');
  }
}

function getAppPath() {
  if (isDev) {
    return __dirname.replace(/\\/g, '/').replace('/electron', '');
  } else {
    return process.resourcesPath;
  }
}

function startPHPServer() {
  return new Promise((resolve, reject) => {
    const phpPath = getPhpPath();
    const appPath = getAppPath();
    const artisanPath = path.join(appPath, 'artisan');

    if (!fs.existsSync(artisanPath)) {
      console.error('Artisan file not found');
      reject(new Error('Artisan file not found'));
      return;
    }

    phpServer = spawn(phpPath, [
      artisanPath,
      'serve',
      '--host=127.0.0.1',
      '--port=8000'
    ], {
      cwd: appPath,
      shell: true
    });

    phpServer.stdout.on('data', (data) => {
      console.log(`PHP: ${data}`);
    });

    phpServer.stderr.on('data', (data) => {
      console.error(`PHP Error: ${data}`);
    });

    setTimeout(resolve, 5000);
  });
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1600,
    height: 900,
    minWidth: 800,
    minHeight: 600,
    frame: false,  // Remove default frame
    titleBarStyle: 'hidden',
    backgroundColor: '#1e1e1e',
    webPreferences: {
      nodeIntegration: false,
      contextIsolation: true,
      preload: path.join(__dirname, 'preload.js')
    },
    title: 'RMCS Payroll System',
    icon: path.join(__dirname, '../public/assets/img/favicon/favicon.ico'),
    show: false  // Don't show until ready
  });

  mainWindow.loadURL('http://127.0.0.1:8000');

  // Show window when ready
  mainWindow.once('ready-to-show', () => {
    mainWindow.show();
  });
  /*
  if (isDev) {
    mainWindow.webContents.openDevTools();
  }
  */

  mainWindow.on('closed', () => {
    mainWindow = null;
  });
}

// Window control handlers
ipcMain.on('window-minimize', () => {
  if (mainWindow) mainWindow.minimize();
});

ipcMain.on('window-maximize', () => {
  if (mainWindow) {
    if (mainWindow.isMaximized()) {
      mainWindow.unmaximize();
    } else {
      mainWindow.maximize();
    }
  }
});

ipcMain.on('window-close', () => {
  if (mainWindow) mainWindow.close();
});

app.whenReady().then(async () => {
  try {
    if (!isDev) {
      await startPHPServer();
    }
    createWindow();
  } catch (error) {
    console.error('Failed to start:', error);
  }
});

app.on('window-all-closed', () => {
  if (phpServer) phpServer.kill();
  app.quit();
});

app.on('before-quit', () => {
  if (phpServer) phpServer.kill();
});

app.on('activate', () => {
  if (BrowserWindow.getAllWindows().length === 0) {
    createWindow();
  }
});