self.addEventListener('push', event => {
  const data = event.data?.json() || { title: 'Notification', body: '' };
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: data.icon || '/images/icons/apple-icon.png',
      badge: '/images/icons/badge.png',
      vibrate: [100, 50, 100],
      data: { url: data.url || '/' }
    })
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(clients.openWindow(event.notification.data.url));
});