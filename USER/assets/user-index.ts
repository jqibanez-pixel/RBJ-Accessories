// @ts-nocheck

// FAQ toggle
    const faqQuestions = document.querySelectorAll('.faq-question');
    faqQuestions.forEach(q => {
      q.addEventListener('click', () => {
        const answer = q.nextElementSibling;
        answer.style.display = answer.style.display === 'block' ? 'none' : 'block';
      });
    });

const coverCarousel = document.getElementById('coverCarousel');
  if (coverCarousel) {
    const coverCards = Array.from(coverCarousel.querySelectorAll('.cover-card'));
    const coverLightbox = document.getElementById('coverLightbox');
    const coverLightboxImage = document.getElementById('coverLightboxImage');
    const coverLightboxCaption = document.getElementById('coverLightboxCaption');
    const coverLightboxClose = document.getElementById('coverLightboxClose');
    const stageCounter = document.getElementById('coverStageCounter');
    const prevBtn = document.getElementById('coverPrevBtn');
    const nextBtn = document.getElementById('coverNextBtn');
    const dotsWrap = document.getElementById('coverDots');
    const rotateInterval = Math.max(1800, Number(coverCarousel.dataset.interval) || 2800);
    let activeIndex = 0;
    let rotateTimer = null;
    const dots = [];

    function toCircularOffset(cardIndex, centerIndex) {
      const total = coverCards.length;
      let diff = cardIndex - centerIndex;
      if (diff > total / 2) diff -= total;
      if (diff < -total / 2) diff += total;
      return diff;
    }

    function updateCenterFocus(index, userAction) {
      if (!coverCards.length) return;
      activeIndex = (index + coverCards.length) % coverCards.length;

      coverCards.forEach((card, idx) => {
        const offset = toCircularOffset(idx, activeIndex);
        card.classList.remove('is-left', 'is-center', 'is-right', 'is-hidden');
        card.setAttribute('role', 'button');
        card.setAttribute('tabindex', '0');
        card.setAttribute('aria-selected', idx === activeIndex ? 'true' : 'false');

        if (offset === 0) {
          card.classList.add('is-center');
        } else if (offset === -1) {
          card.classList.add('is-left');
        } else if (offset === 1) {
          card.classList.add('is-right');
        } else {
          card.classList.add('is-hidden');
        }
      });

      if (stageCounter) {
        stageCounter.textContent = (activeIndex + 1) + ' / ' + coverCards.length;
      }

      dots.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === activeIndex);
      });
      if (userAction) restartAutoRotate();
    }

    function startAutoRotate() {
      if (rotateTimer || !coverCards.length) return;
      rotateTimer = setInterval(() => updateCenterFocus(activeIndex + 1, false), rotateInterval);
    }

    function stopAutoRotate() {
      if (!rotateTimer) return;
      clearInterval(rotateTimer);
      rotateTimer = null;
    }

    function restartAutoRotate() {
      stopAutoRotate();
      startAutoRotate();
    }

    function openCoverLightbox(imageSrc, imageAlt, captionText) {
      if (!coverLightbox || !coverLightboxImage) return;
      stopAutoRotate();
      coverLightboxImage.src = imageSrc;
      coverLightboxImage.alt = imageAlt || 'Featured build';
      if (coverLightboxCaption) {
        coverLightboxCaption.textContent = captionText || imageAlt || 'Featured build';
      }
      coverLightbox.classList.add('show');
      coverLightbox.setAttribute('aria-hidden', 'false');
    }

    function closeCoverLightbox() {
      if (!coverLightbox) return;
      coverLightbox.classList.remove('show');
      coverLightbox.setAttribute('aria-hidden', 'true');
      if (coverLightboxImage) coverLightboxImage.src = '';
      startAutoRotate();
    }

    if (dotsWrap) {
      coverCards.forEach((_, idx) => {
        const dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Go to featured build ' + (idx + 1));
        dot.addEventListener('click', () => updateCenterFocus(idx, true));
        dotsWrap.appendChild(dot);
        dots.push(dot);
      });
    }

    coverCards.forEach((card, idx) => {
      card.addEventListener('click', () => {
        if (idx !== activeIndex) {
          updateCenterFocus(idx, true);
          return;
        }
        const img = card.querySelector('img');
        const caption = card.querySelector('.cover-caption');
        if (img) {
          openCoverLightbox(img.src, img.alt, caption ? caption.textContent.trim() : '');
        }
      });
      card.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          if (idx !== activeIndex) {
            updateCenterFocus(idx, true);
          } else {
            const img = card.querySelector('img');
            const caption = card.querySelector('.cover-caption');
            if (img) {
              openCoverLightbox(img.src, img.alt, caption ? caption.textContent.trim() : '');
            }
          }
          return;
        }
        if (e.key === 'ArrowRight') {
          e.preventDefault();
          updateCenterFocus(activeIndex + 1, true);
        } else if (e.key === 'ArrowLeft') {
          e.preventDefault();
          updateCenterFocus(activeIndex - 1, true);
        }
      });
    });

    if (prevBtn) prevBtn.addEventListener('click', () => updateCenterFocus(activeIndex - 1, true));
    if (nextBtn) nextBtn.addEventListener('click', () => updateCenterFocus(activeIndex + 1, true));
    if (coverLightboxClose) coverLightboxClose.addEventListener('click', closeCoverLightbox);
    if (coverLightbox) {
      coverLightbox.addEventListener('click', (e) => {
        if (e.target === coverLightbox) closeCoverLightbox();
      });
    }
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && coverLightbox && coverLightbox.classList.contains('show')) {
        closeCoverLightbox();
      }
    });

    updateCenterFocus(0, false);
    startAutoRotate();
  }

  const feedbackForm = document.getElementById('feedbackForm');
  const feedbackResponse = document.getElementById('feedbackResponse');
  const globalToastWrap = document.getElementById('globalToastWrap');
  const scrollTopBtn = document.getElementById('scrollTopBtn');
  const nav = document.querySelector('.navbar');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function getStickyOffset() {
    return (nav ? nav.offsetHeight : 96) + 10;
  }

  function scrollToHash(hash, pushHistory) {
    if (!hash || hash === '#') return;
    const target = document.querySelector(hash);
    if (!target) return;

    const y = target.getBoundingClientRect().top + window.scrollY - getStickyOffset();
    window.scrollTo({
      top: Math.max(0, y),
      behavior: reduceMotion ? 'auto' : 'smooth'
    });

    if (pushHistory) {
      history.pushState(null, '', hash);
    }
  }

  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    link.addEventListener('click', (e) => {
      const hash = link.getAttribute('href');
      if (!hash || hash === '#') return;
      const target = document.querySelector(hash);
      if (!target) return;
      e.preventDefault();
      scrollToHash(hash, true);
    });
  });

  if (window.location.hash) {
    setTimeout(() => scrollToHash(window.location.hash, false), 0);
  }

  const sectionLinks = Array.from(document.querySelectorAll('.nav-links a[href^="#"]'));
  const sectionMap = new Map();
  sectionLinks.forEach((link) => {
    const hash = link.getAttribute('href');
    const section = hash ? document.querySelector(hash) : null;
    if (section) sectionMap.set(section, link);
  });

  if ('IntersectionObserver' in window && sectionMap.size) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        const link = sectionMap.get(entry.target);
        if (!link) return;
        if (entry.isIntersecting) {
          sectionLinks.forEach((item) => item.classList.remove('active-section'));
          link.classList.add('active-section');
        }
      });
    }, {
      root: null,
      rootMargin: '-40% 0px -45% 0px',
      threshold: 0
    });

    sectionMap.forEach((_, section) => observer.observe(section));
  }

  if (scrollTopBtn) {
    let ticking = false;
    const toggleTopBtn = () => {
      const show = window.scrollY > 450;
      scrollTopBtn.classList.toggle('show', show);
      ticking = false;
    };

    window.addEventListener('scroll', () => {
      if (!ticking) {
        window.requestAnimationFrame(toggleTopBtn);
        ticking = true;
      }
    }, { passive: true });

    toggleTopBtn();
    scrollTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }

  const locatorMapFrame = document.getElementById('locatorMapFrame');
  const locatorMapModalFrame = document.getElementById('locatorMapModalFrame');
  const locatorMapModal = document.getElementById('locatorMapModal');
  const locatorExpandBtn = document.getElementById('locatorExpandBtn');
  const locatorMapModalClose = document.getElementById('locatorMapModalClose');
  const branchItems = Array.from(document.querySelectorAll('.branch-item[data-map-query]'));
  const directionButtons = Array.from(document.querySelectorAll('.branch-direction-btn[data-destination]'));

  function setActiveBranch(item) {
    branchItems.forEach((branch) => branch.classList.remove('is-active'));
    if (item) item.classList.add('is-active');
  }

  function updateLocatorMap(queryText) {
    if (!locatorMapFrame || !queryText) return;
    const src = 'https://www.google.com/maps?q=' + encodeURIComponent(queryText) + '&output=embed';
    locatorMapFrame.src = src;
    if (locatorMapModalFrame) locatorMapModalFrame.src = src;
  }

  function updateLocatorDirections(destination, origin) {
    if (!locatorMapFrame || !destination) return;
    const params = new URLSearchParams();
    params.set('output', 'embed');
    params.set('f', 'd');
    params.set('dirflg', 'd');
    params.set('daddr', destination);
    params.set('saddr', origin || 'Current Location');
    const src = 'https://www.google.com/maps?' + params.toString();
    locatorMapFrame.src = src;
    if (locatorMapModalFrame) locatorMapModalFrame.src = src;
  }

  function openLocatorMapModal() {
    if (!locatorMapModal) return;
    if (locatorMapFrame && locatorMapModalFrame) {
      locatorMapModalFrame.src = locatorMapFrame.src;
    }
    locatorMapModal.classList.add('show');
    locatorMapModal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  const LOCATOR_TARGET_ACCURACY_METERS = 120;

  async function resolveBestOriginCoords() {
    if (!navigator.geolocation) {
      return { origin: '', accuracy: Infinity, errorType: 'unsupported' };
    }

    const bestSample = await new Promise((resolve) => {
      let best = null;
      let settled = false;
      let sampleCount = 0;
      const maxSamples = 8;
      let watchId = null;

      const finish = (result) => {
        if (settled) return;
        settled = true;
        if (watchId !== null) navigator.geolocation.clearWatch(watchId);
        resolve(result);
      };

      watchId = navigator.geolocation.watchPosition((pos) => {
        sampleCount += 1;
        if (!best || pos.coords.accuracy < best.coords.accuracy) {
          best = pos;
        }
        if (best.coords.accuracy <= LOCATOR_TARGET_ACCURACY_METERS || sampleCount >= maxSamples) {
          finish(best);
        }
      }, (err) => {
        finish({ error: err, best });
      }, {
        enableHighAccuracy: true,
        timeout: 30000,
        maximumAge: 0
      });

      setTimeout(() => finish(best || { error: { code: 3 } }), 32000);
    });

    if (bestSample && bestSample.error) {
      const fallbackBest = bestSample.best || null;
      if (fallbackBest && typeof fallbackBest.coords.accuracy === 'number') {
        return {
          origin: fallbackBest.coords.latitude + ',' + fallbackBest.coords.longitude,
          accuracy: fallbackBest.coords.accuracy,
          errorType: 'partial'
        };
      }
      return { origin: '', accuracy: Infinity, errorType: 'denied_or_timeout' };
    }

    if (!bestSample || !bestSample.coords) {
      return { origin: '', accuracy: Infinity, errorType: 'unknown' };
    }

    return {
      origin: bestSample.coords.latitude + ',' + bestSample.coords.longitude,
      accuracy: typeof bestSample.coords.accuracy === 'number' ? bestSample.coords.accuracy : Infinity,
      errorType: null
    };
  }

  branchItems.forEach((item) => {
    item.addEventListener('click', (e) => {
      if (e.target.closest('.branch-direction-btn')) return;
      const query = item.dataset.mapQuery || '';
      setActiveBranch(item);
      updateLocatorMap(query);
    });

    item.addEventListener('keydown', (e) => {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      e.preventDefault();
      const query = item.dataset.mapQuery || '';
      setActiveBranch(item);
      updateLocatorMap(query);
    });
  });

  directionButtons.forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      const parentBranch = btn.closest('.branch-item');
      const destination = btn.dataset.destination || '';
      if (!destination) return;
      if (parentBranch) setActiveBranch(parentBranch);
      const openDirectionsInLocator = (origin) => {
        updateLocatorDirections(destination, origin);
      };

      // Load directions immediately using Google Maps live current-location resolution.
      openDirectionsInLocator('Current Location');
      openLocatorMapModal();

      const originalLabel = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Locating...';
      const locationResult = await resolveBestOriginCoords();
      btn.disabled = false;
      btn.textContent = originalLabel;
      if (!locationResult.origin) {
        showGlobalToast('error', 'Using Google Maps live location for directions.');
        return;
      }
      if (locationResult.accuracy > 300) {
        showGlobalToast('error', 'GPS signal is weak. Keeping Google Maps live location route.');
        return;
      }
      openDirectionsInLocator(locationResult.origin);
    });
  });

  if (branchItems.length) {
    setActiveBranch(branchItems[0]);
  }

  if (locatorExpandBtn && locatorMapModal) {
    locatorExpandBtn.addEventListener('click', openLocatorMapModal);
  }

  function closeLocatorMapModal() {
    if (!locatorMapModal) return;
    locatorMapModal.classList.remove('show');
    locatorMapModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  if (locatorMapModalClose) {
    locatorMapModalClose.addEventListener('click', closeLocatorMapModal);
  }
  if (locatorMapModal) {
    locatorMapModal.addEventListener('click', (e) => {
      if (e.target === locatorMapModal) closeLocatorMapModal();
    });
  }

  function showGlobalToast(type, message) {
    if (!globalToastWrap || !message) return;
    const toast = document.createElement('div');
    toast.className = 'global-toast ' + (type === 'success' ? 'success' : 'error');
    toast.textContent = message;
    globalToastWrap.appendChild(toast);

    setTimeout(() => {
      toast.classList.add('fade-out');
      setTimeout(() => {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 240);
    }, 2600);
  }

  if (feedbackForm) {
    feedbackForm.addEventListener('submit', async function (e) {
      e.preventDefault();

      const submitBtn = feedbackForm.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
      }

      try {
        const formData = new FormData(feedbackForm);
        const res = await fetch('index.php', {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          }
        });

        if (!res.ok) throw new Error('Request failed');
        const data = await res.json();

        if (data.ok) {
          showGlobalToast('success', data.message || 'Feedback submitted successfully.');
          feedbackForm.reset();
          if (feedbackResponse) feedbackResponse.innerHTML = '';
        } else {
          showGlobalToast('error', data.message || 'Failed to submit feedback.');
        }
      } catch (err) {
        showGlobalToast('error', 'Could not submit feedback right now. Please try again.');
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalBtnText;
        }
      }
    });
  }


