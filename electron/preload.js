const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    minimize: () => ipcRenderer.send('window-minimize'),
    maximize: () => ipcRenderer.send('window-maximize'),
    close: () => ipcRenderer.send('window-close'),
    showContextMenu: (position) => ipcRenderer.send('show-context-menu', position),
    onMaximizeChange: (callback) => ipcRenderer.on('maximize-change', (event, isMaximized) => callback(isMaximized))
});