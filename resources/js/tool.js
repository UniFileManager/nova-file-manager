import FileManagerTool from './views/FileManagerTool.vue'
import UniFilePicker from './fields/UniFilePicker.vue'
import './styles.css'

Nova.booting((app) => {
  if (typeof Nova.inertia === 'function') {
    Nova.inertia('UniFileManager', FileManagerTool)
  }

  app.component('unifilemanager-nova-file-manager', FileManagerTool)
  app.component('index-uni-file-picker', UniFilePicker)
  app.component('detail-uni-file-picker', UniFilePicker)
  app.component('form-uni-file-picker', UniFilePicker)
})
