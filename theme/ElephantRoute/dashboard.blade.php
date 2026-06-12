<!doctype html>
<html lang="zh-CN">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,minimum-scale=1,user-scalable=no" />
  <title>{{$title}}</title>
  <link rel="stylesheet" href="/theme/{{$theme}}/assets/elephant-route-auth.css?v={{$version}}-er20260515d">
  <link rel="stylesheet" href="/theme/{{$theme}}/assets/elephant-route-dashboard.css?v={{$version}}-er20260609clashVerge1">
  <script type="module" crossorigin src="/theme/{{$theme}}/assets/umi.js?v={{$version}}-er20260530hiderate1"></script>
  
  <!-- Fluid Ripple Effect Styles -->
  <style>
    /* Canvas container for ripple effect */
    #ripple-canvas {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 0;
    }

    /* Ensure app content is above canvas */
    #app {
      position: relative;
      z-index: 1;
    }
  </style>

</head>

<body>

  <!-- Fluid Ripple Effect Canvas -->
  <canvas id="ripple-canvas"></canvas>

  <script>
    window.routerBase = "/";
    window.settings = {
      title: '{{$title}}',
      assets_path: '/theme/{{$theme}}/assets',
      theme: {
        color: '{{ $theme_config['theme_color'] ?? "default" }}',
      },
      version: '{{$version}}',
      background_url: '{{$theme_config['background_url']}}',
      description: '{{$description}}',
      i18n: [
        'zh-CN',
        'en-US',
        'ja-JP',
        'vi-VN',
        'ko-KR',
        'zh-TW',
        'fa-IR'
      ],
      logo: '{{$logo}}'
    }
  </script>
  <div id="app"></div>
  {!! $theme_config['custom_html'] !!}
  <script src="/theme/{{$theme}}/assets/elephant-route-auth.js?v={{$version}}-er20260524fix1"></script>
  <script src="/theme/{{$theme}}/assets/elephant-route-dashboard.js?v={{$version}}-er20260611difyNoContext1"></script>
  <script>
    (function() {
      function handleDownloadRedirect() {
        const hash = window.location.hash || '';
        const queryStart = hash.indexOf('?');
        if (queryStart === -1) return;

        const query = new URLSearchParams(hash.slice(queryStart + 1));
        const target = query.get('download_redirect');
        if (!target) return;

        const decodedTarget = decodeURIComponent(target);
        if (!decodedTarget.startsWith('/download/')) return;

        window.location.replace(decodedTarget);
      }

      function insertLoginLogo() {
        // Auth pages are handled by elephant-route-auth.js.
        return;
      }

      function insertSidebarLogo() {
        // Only run if NOT on login page
        if (window.location.hash.startsWith('#/login')) return;

        // Prevent duplicate injection
        if (document.getElementById('custom-sidebar-brand-row')) return;

        // Try multiple selectors
        let titleEl = document.querySelector('.title-text') || 
                      document.querySelector('h2');

        // Fallback: finding the sidebar content container if title specific class missing
        if (!titleEl) {
            const sidebar = document.querySelector('.n-layout-sider');
            if (sidebar) {
                 const scrollbar = sidebar.querySelector('.n-scrollbar');
                 if (scrollbar && scrollbar.firstChild) {
                     // Check if first child seems to be the title (text node or element with text)
                     // Using the scrollbar's first child as the reference to insert BEFORE
                     titleEl = scrollbar.firstChild;
                 }
            }
        }

        if (titleEl && titleEl.parentNode) {
            console.log('Xboard Theme: Found sidebar target, injecting logo.');
            const brandRow = document.createElement('div');
            brandRow.id = 'custom-sidebar-brand-row';
            brandRow.className = 'er-sidebar-brand-row';

            const logo = document.createElement('img');
            logo.src = '/home_logo.jpeg';
            logo.id = 'custom-sidebar-logo';
            logo.alt = '大象网络';
            logo.className = 'er-sidebar-brand-logo';

            if (titleEl.nodeType === Node.ELEMENT_NODE) {
              titleEl.classList.add('er-sidebar-brand-title');
            }

            titleEl.parentNode.insertBefore(brandRow, titleEl);
            brandRow.appendChild(logo);
            brandRow.appendChild(titleEl);
        }
      }

      // Make logo clickable to navigate to landing page
      function makeLogoClickable() {
        const logo = document.getElementById('custom-login-logo');
        if (logo && !logo.hasAttribute('data-clickable')) {
          logo.setAttribute('data-clickable', 'true');
          logo.style.cursor = 'pointer';
          logo.style.transition = 'opacity 0.2s';
          
          logo.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Logo clicked! Navigating to home page');
            window.location.assign(window.location.origin + '/');
          });
          
          logo.addEventListener('mouseenter', function() {
            logo.style.opacity = '0.8';
          });
          
          logo.addEventListener('mouseleave', function() {
            logo.style.opacity = '1';
          });
          
          console.log('Logo made clickable');
        }
      }

      // Use a combination of MutationObserver and setInterval for robustness
      const observer = new MutationObserver((mutations) => {
        handleDownloadRedirect();
        insertLoginLogo();
        insertSidebarLogo();
        makeLogoClickable();
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true
      });

      // Periodic check in case MutationObserver misses it (common in some frameworks)
      setInterval(() => {
          handleDownloadRedirect();
          insertLoginLogo();
          insertSidebarLogo();
          makeLogoClickable();
      }, 1000);

      // Initial try
      handleDownloadRedirect();
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            handleDownloadRedirect();
            insertLoginLogo();
            insertSidebarLogo();
        });
      } else {
        insertLoginLogo();
        insertSidebarLogo();
      }
    })();


    // Mouse Particle Effect (same as landing page)
    (function() {
      const canvas = document.getElementById('ripple-canvas');
      
      if (!canvas) return;

      const ctx = canvas.getContext('2d');
      let particles = [];
      let animationId;
      
      // Set canvas size
      function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
      }
      
      resizeCanvas();
      window.addEventListener('resize', resizeCanvas);
      
      // Particle class
      function createParticle(x, y) {
        const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEEAD'];
        return {
          x: x,
          y: y,
          vx: (Math.random() - 0.5) * 2,
          vy: (Math.random() - 0.5) * 2,
          life: 1,
          size: Math.random() * 3 + 1,
          color: colors[Math.floor(Math.random() * colors.length)]
        };
      }
      
      // Show particle effect on login and register pages
      function updateParticleVisibility() {
        const hash = window.location.hash;
        if (hash.includes('login') || hash.includes('register')) {
          canvas.style.display = 'block';
          if (!animationId) animate();
        } else {
          canvas.style.display = 'none';
          particles = [];
          if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
          }
        }
      }
      
      // Initial check
      updateParticleVisibility();
      
      // Listen to hash changes
      window.addEventListener('hashchange', updateParticleVisibility);
      
      // Mouse move handler
      document.addEventListener('mousemove', function(e) {
        if (canvas.style.display === 'none') return;
        
        // Spawn 3 particles on mouse move
        for (let i = 0; i < 3; i++) {
          particles.push(createParticle(e.clientX, e.clientY));
        }
      });
      
      // Animation loop
      function animate() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Update and draw particles
        for (let i = particles.length - 1; i >= 0; i--) {
          const p = particles[i];
          p.x += p.vx;
          p.y += p.vy;
          p.life -= 0.02;
          p.size *= 0.95;
          
          // Remove dead particles
          if (p.life <= 0 || p.size < 0.1) {
            particles.splice(i, 1);
            continue;
          }
          
          // Draw particle
          ctx.beginPath();
          ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
          ctx.fillStyle = p.color;
          ctx.globalAlpha = p.life;
          ctx.fill();
          ctx.globalAlpha = 1;
        }
        
        // Connect particles with lines
        ctx.strokeStyle = 'rgba(100, 100, 100, 0.1)';
        ctx.lineWidth = 0.5;
        
        for (let i = 0; i < particles.length; i++) {
          for (let j = i + 1; j < particles.length; j++) {
            const dx = particles[i].x - particles[j].x;
            const dy = particles[i].y - particles[j].y;
            const distance = Math.sqrt(dx * dx + dy * dy);
            
            if (distance < 100) {
              ctx.beginPath();
              ctx.moveTo(particles[i].x, particles[i].y);
              ctx.lineTo(particles[j].x, particles[j].y);
              ctx.stroke();
            }
          }
        }
        
        animationId = requestAnimationFrame(animate);
      }
    })();
  </script>
</body>

</html>
