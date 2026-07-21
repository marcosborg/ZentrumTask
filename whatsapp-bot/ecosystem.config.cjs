module.exports = {
  apps: [
    {
      name: 'zentrum-whatsapp-bot',
      script: './server.js',
      cwd: __dirname,
      exec_mode: 'fork',
      instances: 1,
      autorestart: true,
      restart_delay: 5000,
      max_memory_restart: '650M',
      time: true,
      env: {
        NODE_ENV: 'production',
      },
    },
  ],
};
