importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-app.js');
importScripts('https://www.gstatic.com/firebasejs/8.10.1/firebase-messaging.js');

// ==========================================
// 🚨 TODO: ให้นำค่าจาก Firebase Console มาใส่ที่นี่
// ไปที่ Project Settings -> General -> Web App
// ==========================================
const firebaseConfig = {
  apiKey: "AIzaSyDha27EeFxPmyoTD2o1WhMzjyxiSGe9Kw8",
  authDomain: "apis-1cd5e.firebaseapp.com",
  projectId: "apis-1cd5e",
  storageBucket: "apis-1cd5e.firebasestorage.app",
  messagingSenderId: "718694613926",
  appId: "1:718694613926:web:abc7ae0077ddda732d8567",
  measurementId: "G-R26KN2WVMB" // <--- เปลี่ยนตรงนี้
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

messaging.onBackgroundMessage((payload) => {
  console.log('[firebase-messaging-sw.js] Received background message ', payload);

  const notificationTitle = payload.notification?.title || 'มีการแจ้งเตือนใหม่';
  const notificationOptions = {
    body: payload.notification?.body || '',
    icon: '/assets/images/logo.png', // เปลี่ยนเป็น path โลโก้ของคุณถ้ามี
  };

  self.registration.showNotification(notificationTitle, notificationOptions);
});
