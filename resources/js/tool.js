import FileManagerTool from './views/FileManagerTool.vue'
import UniFilePicker from './fields/UniFilePicker.vue'

Nova.booting((app) => {
  app.component('unifilemanager-nova-file-manager', FileManagerTool)
  app.component('uni-file-picker', UniFilePicker)
})
