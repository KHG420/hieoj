(() => {
  'use strict';
  const clock = document.getElementById('adv-clock');
  if (clock) {
    const opened = Date.now();
    const elapsed = Number(clock.dataset.elapsed);
    const duration = Number(clock.dataset.duration);
    const tick = () => {
      const seconds = Math.min(duration, elapsed + Math.floor((Date.now() - opened) / 1000));
      clock.textContent = seconds >= duration ? '计时结束 · 刷新查看战报' : `已进行 ${Math.floor(seconds / 60)} 分 ${seconds % 60} 秒`;
    };
    tick();
    const timer = setInterval(tick, 1000);
    window.addEventListener('pagehide', () => clearInterval(timer), {once:true});
  }
  const source = document.getElementById('adv-card-data');
  const cards = source ? JSON.parse(source.textContent) : {};
  document.querySelectorAll('[data-save-card]').forEach(button => {
    button.addEventListener('click', () => {
      const card = cards[button.dataset.saveCard];
      const status = document.getElementById('adv-save-status');
      if (!card) return;
      button.disabled = true;
      status.textContent = '正在生成回忆卡…';
      const canvas = document.createElement('canvas');
      canvas.width = 1200; canvas.height = 900;
      const ctx = canvas.getContext('2d');
      if (!ctx) { status.textContent = '浏览器无法生成图片，请尝试使用浏览器打印保存。'; button.disabled = false; return; }
      ctx.fillStyle = '#f4f6fa'; ctx.fillRect(0, 0, 1200, 900);
      ctx.fillStyle = '#ffffff'; ctx.fillRect(56, 56, 1088, 788);
      ctx.fillStyle = '#192c46'; ctx.font = 'bold 48px sans-serif'; ctx.fillText(card.title, 100, 150);
      ctx.fillStyle = '#dce3ec'; ctx.fillRect(100, 190, 1000, 2);
      ctx.fillStyle = '#40516a'; ctx.font = '30px sans-serif';
      let y = 265;
      card.lines.forEach(line => {
        const characters = Array.from(line);
        for (let row = 0; row < 2 && characters.length; row++) {
          let current = '';
          while (characters.length && ctx.measureText(current + characters[0] + (row === 1 ? '…' : '')).width <= 980) current += characters.shift();
          if (row === 1 && characters.length) current += '…';
          ctx.fillText(current, 100, y); y += 43;
        }
        y += 25;
      });
      ctx.fillStyle = '#245dd8'; ctx.font = '24px sans-serif'; ctx.fillText('今日冒险 · ' + new Date().toLocaleDateString('zh-CN'), 100, 795);
      canvas.toBlob(blob => {
        button.disabled = false;
        if (!blob) { status.textContent = '图片生成失败，请重试。'; return; }
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url; link.download = button.dataset.saveCard + '.png'; link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
        status.textContent = '回忆卡已生成，请在浏览器下载中查看。';
      }, 'image/png');
    });
  });
})();
