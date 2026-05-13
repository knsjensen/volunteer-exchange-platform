/**
 * Admin JavaScript for Volunteer Exchange Platform
 */

(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        initEventDisplay();
        initDisplayBackgroundSettings();
        initCompetitionsPage();
        initCopyActiveEmails();
        initAdminChoices();
    });

    function initCopyActiveEmails() {
        const button = document.getElementById('vep-copy-active-emails');
        if (!button || typeof vepAdmin === 'undefined') {
            return;
        }

        const ajaxUrl = vepAdmin.ajaxUrl;
        const i18n = vepAdmin.i18n || {};

        const copyText = (text) => {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                return navigator.clipboard.writeText(text);
            }

            return new Promise((resolve, reject) => {
                const helper = document.createElement('textarea');
                helper.value = text;
                helper.setAttribute('readonly', 'readonly');
                helper.style.position = 'absolute';
                helper.style.left = '-9999px';
                document.body.appendChild(helper);
                helper.select();

                try {
                    const ok = document.execCommand('copy');
                    document.body.removeChild(helper);
                    if (ok) {
                        resolve();
                    } else {
                        reject(new Error('Copy command failed'));
                    }
                } catch (error) {
                    document.body.removeChild(helper);
                    reject(error);
                }
            });
        };

        button.addEventListener('click', () => {
            const eventId = button.dataset.eventId;
            const nonce = button.dataset.nonce;

            if (!eventId || !nonce) {
                window.alert(i18n.copyActiveEmailsFailed || 'An error occurred. Please try again.');
                return;
            }

            button.disabled = true;

            const formData = new FormData();
            formData.append('action', 'vep_get_active_participant_emails');
            formData.append('event_id', eventId);
            formData.append('nonce', nonce);

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.success || !data.data || !data.data.emails_text) {
                        throw new Error((data.data && data.data.message) || (i18n.noActiveEmailsFound || 'No active participant emails found.'));
                    }

                    return copyText(data.data.emails_text)
                        .then(() => {
                            const messageTemplate = i18n.copyActiveEmailsSuccess || 'Copied %d active participant emails to clipboard.';
                            const count = (data.data && data.data.count) ? String(data.data.count) : '0';
                            window.alert(messageTemplate.replace('%d', count));
                        });
                })
                .catch((error) => {
                    window.alert(error.message || i18n.copyActiveEmailsFailed || 'An error occurred. Please try again.');
                })
                .finally(() => {
                    button.disabled = false;
                });
        });
    }
    
    /**
     * Initialize Event Display functionality
     */
    function initEventDisplay() {
        const startButton = document.getElementById('vep-start-event-display');
        const displayModal = document.getElementById('vep-fullscreen-display');
        const closeButton = document.getElementById('vep-close-display');
        const displayMainContent = document.getElementById('vep-display-main-content');
        const rightPanel = document.getElementById('vep-display-right');
        const postTimeActions = document.getElementById('vep-post-time-actions');
        const statisticsView = document.getElementById('vep-statistics-view');
        
        if (!startButton || !displayModal) return;
        
        let countdownInterval = null;
        let pollingInterval = null;
        let fireworksAnimation = null;
        let fireworksStopTimeout = null;
        let postTimeRevealTimeout = null;
        
        // Start display
        startButton.addEventListener('click', function() {
            const countdownTime = this.dataset.eventEnd || this.dataset.countdown;
            const eventId = this.dataset.eventId;
            const displayTitle = this.dataset.displayTitle;
            const displayMode = this.dataset.displayMode || 'leaderboard';
            const backgroundType = this.getAttribute('data-background-type') || 'gradient';
            const solidColor = this.getAttribute('data-background-solid-color') || '#1e3c72';
            const gradientColor1 = this.getAttribute('data-background-gradient-color-1') || '#1e3c72';
            const gradientColor2 = this.getAttribute('data-background-gradient-color-2') || '#2a5298';
            const gradientColor3 = this.getAttribute('data-background-gradient-color-3') || '#7e22ce';
            const gradientStop1 = parseInt(this.getAttribute('data-background-gradient-stop-1'), 10);
            const gradientStop2 = parseInt(this.getAttribute('data-background-gradient-stop-2'), 10);
            const gradientStop3 = parseInt(this.getAttribute('data-background-gradient-stop-3'), 10);
            const gradientAngle = parseInt(this.getAttribute('data-background-gradient-angle'), 10);
            const textColor = this.getAttribute('data-text-color') || '#ffffff';
            
            if (!countdownTime) {
                alert(vepAdmin.i18n.setCountdownTimeFirst);
                return;
            }
            
            // Show modal
            displayModal.style.display = 'flex';
            
            // Set display title
            document.getElementById('vep-display-event-name').textContent = displayTitle;

            applyRightPanelMode(displayMode);

            // Apply selected background style
            applyDisplayBackground(
                displayModal,
                backgroundType,
                solidColor,
                gradientColor1,
                gradientColor2,
                gradientColor3,
                Number.isFinite(gradientStop1) ? gradientStop1 : 0,
                Number.isFinite(gradientStop2) ? gradientStop2 : 50,
                Number.isFinite(gradientStop3) ? gradientStop3 : 100,
                Number.isFinite(gradientAngle) ? gradientAngle : 135
            );

            applyDisplayTextColor(displayModal, textColor);

            if (postTimeActions) {
                postTimeActions.style.display = 'none';
            }

            if (statisticsView) {
                statisticsView.style.display = 'none';
            }

            if (displayMainContent) {
                displayMainContent.style.display = '';
            }

            if (postTimeRevealTimeout) {
                clearTimeout(postTimeRevealTimeout);
                postTimeRevealTimeout = null;
            }

            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = 'none';
            
            // Request fullscreen
            requestFullscreen(displayModal);
            
            // Start countdown
            const autoSwitchStats = this.dataset.autoSwitchStats === '1';
            startCountdown(countdownTime, autoSwitchStats);
            
            // Start polling for agreement count
            startPolling(eventId, displayMode);
        });
        
        // Show statistics view
        const showStatisticsBtn = document.getElementById('vep-show-statistics');
        if (showStatisticsBtn) {
            showStatisticsBtn.addEventListener('click', function() {
                showStatisticsView(startButton.dataset.eventId);
            });
        }

        // Close display
        closeButton.addEventListener('click', closeDisplay);
        
        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && displayModal.style.display === 'flex') {
                closeDisplay();
            }
        });
        
        function requestFullscreen(element) {
            if (element.requestFullscreen) {
                element.requestFullscreen();
            } else if (element.webkitRequestFullscreen) {
                element.webkitRequestFullscreen();
            } else if (element.msRequestFullscreen) {
                element.msRequestFullscreen();
            }
        }
        
        function exitFullscreen() {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }
        
        function closeDisplay() {
            // Stop intervals
            if (countdownInterval) clearInterval(countdownInterval);
            stopFireworks();
            if (pollingInterval) clearInterval(pollingInterval);
            if (postTimeRevealTimeout) {
                clearTimeout(postTimeRevealTimeout);
                postTimeRevealTimeout = null;
            }

            if (postTimeActions) {
                postTimeActions.style.display = 'none';
            }

            if (statisticsView) {
                statisticsView.style.display = 'none';
            }

            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = 'none';

            if (displayMainContent) {
                displayMainContent.style.display = '';
            }
            
            // Exit fullscreen
            exitFullscreen();
            
            // Hide modal
            displayModal.style.display = 'none';
        }

        function applyDisplayBackground(modal, backgroundType, solidColor, color1, color2, color3, stop1, stop2, stop3, angle) {
            const clamp = (value) => Math.min(100, Math.max(0, value));
            const clampAngle = (value) => Math.min(360, Math.max(0, value));
            const safeStop1 = clamp(stop1);
            const safeStop2 = clamp(stop2);
            const safeStop3 = clamp(stop3);
            const safeAngle = clampAngle(angle);

            if (backgroundType === 'solid') {
                modal.style.background = solidColor;
                return;
            }

            modal.style.background = `linear-gradient(${safeAngle}deg, ${color1} ${safeStop1}%, ${color2} ${safeStop2}%, ${color3} ${safeStop3}%)`;
        }

        function applyDisplayTextColor(modal, textColor) {
            const color = /^#([A-Fa-f0-9]{6})$/.test(textColor) ? textColor : '#ffffff';
            const r = parseInt(color.slice(1, 3), 16);
            const g = parseInt(color.slice(3, 5), 16);
            const b = parseInt(color.slice(5, 7), 16);

            modal.style.setProperty('--vep-display-text-color', color);
            modal.style.setProperty('--vep-display-text-strong', `rgba(${r}, ${g}, ${b}, 0.95)`);
            modal.style.setProperty('--vep-display-text-muted', `rgba(${r}, ${g}, ${b}, 0.8)`);
            modal.style.setProperty('--vep-display-text-soft', `rgba(${r}, ${g}, ${b}, 0.7)`);
        }

        function applyRightPanelMode(displayMode) {
            const showRightPanel = displayMode !== 'none';

            if (rightPanel) {
                rightPanel.style.display = showRightPanel ? '' : 'none';
            }

            if (displayMainContent) {
                displayMainContent.classList.toggle('vep-display-main-content-no-right', !showRightPanel);
            }
        }
        
        function startCountdown(targetDatetime, autoSwitchStats) {
            const targetTimestamp = Math.floor(new Date(targetDatetime).getTime() / 1000);
            const timerElement = displayModal.querySelector('.vep-countdown-timer');
            const expiredElement = displayModal.querySelector('.vep-countdown-expired');
            let alreadyExpiredAtStart = false;
            
            // Reset display states
            timerElement.style.display = '';
            expiredElement.style.display = 'none';
            if (postTimeActions) {
                postTimeActions.style.display = 'none';
            }

            // Detect immediately if the event is already over before we start.
            if (Math.floor(Date.now() / 1000) >= targetTimestamp) {
                alreadyExpiredAtStart = true;
            }
            
            function updateCountdown() {
                const now = Math.floor(Date.now() / 1000);
                const difference = targetTimestamp - now;
                
                if (difference <= 0) {
                    timerElement.style.display = 'none';
                    expiredElement.style.display = 'block';
                    if (countdownInterval) clearInterval(countdownInterval);

                    // Stop polling for agreement count — event is over.
                    if (pollingInterval) {
                        clearInterval(pollingInterval);
                        pollingInterval = null;
                    }

                    startFireworks();

                    if (postTimeRevealTimeout) {
                        clearTimeout(postTimeRevealTimeout);
                    }

                    // Auto-switch skips the delay entirely; action buttons keep the
                    // 10-second grace period (0 if event was already over at start).
                    const delay = autoSwitchStats ? 0 : (alreadyExpiredAtStart ? 0 : 10000);
                    postTimeRevealTimeout = setTimeout(() => {
                        if (displayModal.style.display !== 'flex') return;
                        if (autoSwitchStats) {
                            showStatisticsView(startButton.dataset.eventId, true);
                        } else {
                            if (postTimeActions) postTimeActions.style.display = 'flex';
                        }
                    }, delay);
                    return;
                }
                
                // Calculate time units (no days, just hours:minutes:seconds)
                const totalHours = Math.floor(difference / 3600);
                const minutes = Math.floor((difference % 3600) / 60);
                const seconds = difference % 60;

                const timeText = `${String(totalHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                const timeEl = document.getElementById('vep-display-timer-time');
                if (timeEl) timeEl.textContent = timeText;
            }
            
            // Initial update
            updateCountdown();
            
            // Update every second
            countdownInterval = setInterval(updateCountdown, 1000);
        }
        
        function startPolling(eventId, displayMode) {
            function fetchAgreementCount() {
                const formData = new FormData();
                formData.append('action', 'vep_get_agreement_count');
                formData.append('nonce', vepAdmin.nonce);
                formData.append('event_id', eventId);
                formData.append('display_mode', displayMode);
                
                fetch(vepAdmin.ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('vep-agreements-count').textContent = data.data.count;

                        if (data.data.display_mode === 'none') {
                            return;
                        }

                        if (data.data.display_mode === 'recent_agreements') {
                            updateRecentAgreements(data.data.recent_agreements);
                        } else {
                            updateLeaderboard(data.data.leaderboard);
                        }
                    }
                })
                .catch(error => console.error('Error fetching agreement count:', error));
            }
            
            // Initial fetch
            fetchAgreementCount();
            
            // Poll every 3 seconds
            pollingInterval = setInterval(fetchAgreementCount, 3000);
        }
        
        function updateLeaderboard(leaderboard) {
            const listElement = document.getElementById('vep-leaderboard-list');
            const titleElement = document.getElementById('vep-display-right-panel-title');
            const iconElement = document.getElementById('vep-display-right-panel-icon');

            if (titleElement) {
                titleElement.textContent = vepAdmin.i18n.topParticipantsTitle;
            }

            if (iconElement) {
                iconElement.style.display = '';
            }
            
            if (!leaderboard || leaderboard.length === 0) {
                listElement.innerHTML = '<p class="vep-leaderboard-empty">' + vepAdmin.i18n.noAgreementsYet + '</p>';
                return;
            }
            
            let html = '';
            leaderboard.forEach((participant, index) => {
                const position = index + 1;
                const medal = position === 1 ? '🥇' : position === 2 ? '🥈' : position === 3 ? '🥉' : position + '.';
                const logoHtml = participant.logo_url 
                    ? `<img src="${participant.logo_url}" alt="${participant.organization_name}" class="vep-leaderboard-logo">`
                    : '<div class="vep-leaderboard-logo-placeholder"></div>';
                
                html += `
                    <div class="vep-leaderboard-item ${position <= 3 ? 'vep-leaderboard-top' : ''}">
                        <div class="vep-leaderboard-position">${medal}</div>
                        ${logoHtml}
                        <div class="vep-leaderboard-info">
                            <div class="vep-leaderboard-name">${participant.organization_name}</div>
                            <div class="vep-leaderboard-count">${participant.agreement_count} ${participant.agreement_count > 1 ? vepAdmin.i18n.agreementPlural : vepAdmin.i18n.agreementSingular}</div>
                        </div>
                    </div>
                `;
            });
            
            listElement.innerHTML = html;
        }

        function updateRecentAgreements(recentAgreements) {
            const listElement = document.getElementById('vep-leaderboard-list');
            const titleElement = document.getElementById('vep-display-right-panel-title');
            const iconElement = document.getElementById('vep-display-right-panel-icon');

            if (titleElement) {
                titleElement.textContent = vepAdmin.i18n.latestAgreementsTitle;
            }

            if (iconElement) {
                iconElement.style.display = 'none';
            }

            if (!recentAgreements || recentAgreements.length === 0) {
                listElement.innerHTML = '<p class="vep-leaderboard-empty">' + vepAdmin.i18n.noRecentAgreementsYet + '</p>';
                return;
            }

            let html = '';
            recentAgreements.forEach((agreement) => {
                const participant1 = agreement.participant1_name || '';
                const participant2 = agreement.participant2_name || '';
                const participant1Id = parseInt(agreement.participant1_id, 10) || 0;
                const participant2Id = parseInt(agreement.participant2_id, 10) || 0;
                const initiatorId = parseInt(agreement.initiator_id, 10) || 0;
                const participant1IsInitiator = participant1Id > 0 && participant1Id === initiatorId;
                const participant2IsInitiator = participant2Id > 0 && participant2Id === initiatorId;

                html += `
                    <div class="vep-recent-agreement-item">
                        <div class="vep-recent-agreement-info">
                            <div class="vep-recent-agreement-actor-row">
                                <span class="vep-recent-agreement-check ${participant1IsInitiator ? 'is-visible' : ''}">${participant1IsInitiator ? '&#10003;' : ''}</span>
                                <span class="vep-recent-agreement-actor-name">${participant1}</span>
                            </div>
                            <div class="vep-recent-agreement-actor-row">
                                <span class="vep-recent-agreement-check ${participant2IsInitiator ? 'is-visible' : ''}">${participant2IsInitiator ? '&#10003;' : ''}</span>
                                <span class="vep-recent-agreement-actor-name">${participant2}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            listElement.innerHTML = html;
        }
        
        function showStatisticsView(eventId, showTimeUp) {
            // Fireworks continue running over the statistics view.

            // Stop countdown interval (polling continues for potential future use).
            if (countdownInterval) clearInterval(countdownInterval);
            if (postTimeRevealTimeout) {
                clearTimeout(postTimeRevealTimeout);
                postTimeRevealTimeout = null;
            }

            // Hide live-display content and action buttons
            if (displayMainContent) displayMainContent.style.display = 'none';
            if (postTimeActions) postTimeActions.style.display = 'none';

            // Show/hide "Tiden er gået" heading
            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = showTimeUp ? '' : 'none';

            // Show statistics view
            if (statisticsView) statisticsView.style.display = 'flex';

            // Fetch statistics from server
            const formData = new FormData();
            formData.append('action', 'vep_get_event_statistics');
            formData.append('nonce', vepAdmin.nonce);
            formData.append('event_id', eventId);

            fetch(vepAdmin.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) throw new Error(data.data && data.data.message);
                renderStatistics(data.data);
            })
            .catch(() => {
                const errorEl = statisticsView ? statisticsView.querySelector('.vep-statistics-error') : null;
                if (errorEl) {
                    errorEl.textContent = vepAdmin.i18n.statsLoadingError || 'Could not load statistics.';
                    errorEl.style.display = 'block';
                }
            });
        }

        function renderStatistics(stats) {
            const totalEl = document.getElementById('vep-stat-total');
            const avgEl   = document.getElementById('vep-stat-avg');
            const maxEl   = document.getElementById('vep-stat-max');
            const firstEl = document.getElementById('vep-stat-first');

            if (totalEl) totalEl.textContent = stats.total;
            if (avgEl)   avgEl.textContent   = stats.avg_per_minute;
            if (maxEl)   maxEl.textContent   = stats.max_by_actor;

            if (firstEl) {
                const s = stats.first_agreement_seconds;
                if (s === null || s === undefined || s < 0) {
                    firstEl.textContent = '—';
                } else if (s < 120) {
                    firstEl.textContent = s + ' sek.';
                } else {
                    firstEl.textContent = Math.round(s / 60) + ' min.';
                }
            }

            drawStatisticsChart(stats.per_minute, stats.duration_minutes);
        }

        function drawStatisticsChart(perMinute, durationMinutes) {
            const chartCanvas = document.getElementById('vep-statistics-chart');
            if (!chartCanvas) return;

            const ctx = chartCanvas.getContext('2d');

            // Measure the rendered size of the canvas element
            const rect  = chartCanvas.getBoundingClientRect();
            const width  = rect.width  || chartCanvas.offsetWidth  || 800;
            const height = rect.height || chartCanvas.offsetHeight || 300;
            chartCanvas.width  = width;
            chartCanvas.height = height;

            // Build full data array, filling minutes with no agreements as 0
            const data = [];
            for (let i = 0; i < durationMinutes; i++) {
                data.push(perMinute[i] || 0);
            }

            const maxVal  = Math.max(1, ...data);
            const padding = { top: 30, right: 20, bottom: 50, left: 50 };
            const cw      = width  - padding.left - padding.right;
            const ch      = height - padding.top  - padding.bottom;
            const barW    = Math.max(1, cw / data.length);

            ctx.clearRect(0, 0, width, height);

            // Always use fixed light colours so the chart is readable on any
            // background. The dark panel behind the statistics view provides contrast.
            const textColor = 'rgba(255, 255, 255, 0.9)';
            const gridColor = 'rgba(255, 255, 255, 0.25)';
            const barColor  = 'rgba(255, 210, 60, 0.92)';

            // Horizontal grid lines + Y-axis labels
            const ySteps = 5;
            ctx.lineWidth = 1;
            for (let i = 0; i <= ySteps; i++) {
                const y = padding.top + ch - (i / ySteps) * ch;
                ctx.strokeStyle = gridColor;
                ctx.beginPath();
                ctx.moveTo(padding.left, y);
                ctx.lineTo(padding.left + cw, y);
                ctx.stroke();

                ctx.fillStyle = textColor;
                ctx.font = '12px sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(Math.round((i / ySteps) * maxVal), padding.left - 6, y + 4);
            }

            // Bars
            data.forEach((val, i) => {
                const barH = (val / maxVal) * ch;
                const x    = padding.left + i * barW;
                const y    = padding.top  + ch - barH;
                ctx.fillStyle = barColor;
                ctx.fillRect(x + 1, y, barW - 2, barH);
            });

            // X-axis label (chart title)
            ctx.fillStyle = textColor;
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(
                vepAdmin.i18n.statsChartLabel || 'Agreements per minute',
                padding.left + cw / 2,
                height - 6
            );

            // X-axis minute ticks (at most 10 visible ticks)
            const tickEvery = Math.max(1, Math.ceil(durationMinutes / 10));
            ctx.font = '11px sans-serif';
            for (let i = 0; i < durationMinutes; i += tickEvery) {
                const x = padding.left + (i + 0.5) * barW;
                ctx.fillText(i, x, padding.top + ch + 16);
            }
        }

        function stopFireworks() {
            if (fireworksAnimation) {
                cancelAnimationFrame(fireworksAnimation);
                fireworksAnimation = null;
            }
            if (fireworksStopTimeout) {
                clearTimeout(fireworksStopTimeout);
                fireworksStopTimeout = null;
            }
            const fwCanvas = document.getElementById('vep-fireworks-canvas');
            if (fwCanvas) fwCanvas.style.display = 'none';
        }

        function startFireworks() {
            const canvas = document.getElementById('vep-fireworks-canvas');
            if (!canvas) {
                console.error('Fireworks canvas not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            // Set canvas size to match window
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            resizeCanvas();
            
            // Show canvas
            canvas.style.display = 'block';
            console.log('Fireworks started!');

            // Auto-stop after 2 minutes regardless of which view is showing.
            if (fireworksStopTimeout) clearTimeout(fireworksStopTimeout);
            fireworksStopTimeout = setTimeout(stopFireworks, 120000);
            
            const particles = [];
            const particleCount = 100;
            
            class Particle {
                constructor(x, y, color) {
                    this.x = x;
                    this.y = y;
                    this.color = color;
                    this.velocity = {
                        x: (Math.random() - 0.5) * 10,
                        y: (Math.random() - 0.5) * 10
                    };
                    this.alpha = 1;
                    this.decay = Math.random() * 0.02 + 0.01;
                    this.size = Math.random() * 3 + 2;
                }
                
                update() {
                    this.velocity.y += 0.1; // Gravity
                    this.x += this.velocity.x;
                    this.y += this.velocity.y;
                    this.alpha -= this.decay;
                }
                
                draw() {
                    ctx.save();
                    ctx.globalAlpha = this.alpha;
                    ctx.fillStyle = this.color;
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();
                }
            }
            
            function createFirework(x, y) {
                const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ffa500', '#ffffff'];
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                for (let i = 0; i < particleCount; i++) {
                    particles.push(new Particle(x, y, color));
                }
            }
            
            function animate() {
                // Clear the canvas fully so the chosen background shows through unchanged.
                // Particle trails are handled by each particle's own alpha decay.
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Update and draw particles
                for (let i = particles.length - 1; i >= 0; i--) {
                    particles[i].update();
                    particles[i].draw();
                    
                    if (particles[i].alpha <= 0) {
                        particles.splice(i, 1);
                    }
                }
                
                // Create new fireworks randomly
                if (Math.random() < 0.05) {
                    const x = Math.random() * canvas.width;
                    const y = Math.random() * (canvas.height / 2);
                    createFirework(x, y);
                }
                
                fireworksAnimation = requestAnimationFrame(animate);
            }
            
            // Create initial burst
            for (let i = 0; i < 5; i++) {
                setTimeout(() => {
                    const x = Math.random() * canvas.width;
                    const y = Math.random() * (canvas.height / 2);
                    createFirework(x, y);
                }, i * 200);
            }
            
            animate();
        }
    }

    function initDisplayBackgroundSettings() {
        const backgroundTypeSelect = document.getElementById('vep_background_type');
        if (!backgroundTypeSelect) {
            return;
        }

        const solidRow = document.getElementById('vep-background-solid-row');
        const gradientRow = document.getElementById('vep-background-gradient-row');
        const previewElement = document.getElementById('vep-background-preview');
        const gradientStopsContainer = document.getElementById('vep-gradient-stops');
        const solidColorInput = document.getElementById('vep_background_solid_color');
        const resetButton = document.getElementById('vep-background-reset');
        const gradientAngleInput = document.getElementById('vep_background_gradient_angle');
        const gradientAngleValue = document.getElementById('vep-gradient-angle-value');
        const gradientAngleRow = document.getElementById('vep-background-gradient-angle-row');
        const textColorInput = document.getElementById('vep_display_text_color');
        const advancedToggleButton = document.getElementById('vep-toggle-advanced-settings');
        const advancedRows = Array.from(document.querySelectorAll('.vep-display-advanced-row'));
        let isAdvancedVisible = false;

        const updateRangeLabels = () => {
            if (!gradientStopsContainer) {
                return;
            }

            gradientStopsContainer.querySelectorAll('.vep-gradient-stop').forEach((stopRow) => {
                const range = stopRow.querySelector('.vep-gradient-stop-range');
                const valueLabel = stopRow.querySelector('.vep-gradient-stop-value');
                if (range && valueLabel) {
                    valueLabel.textContent = `${range.value}%`;
                }
            });
        };

        const updateBackgroundRows = () => {
            if (!isAdvancedVisible) {
                if (solidRow) {
                    solidRow.style.display = 'none';
                }
                if (gradientRow) {
                    gradientRow.style.display = 'none';
                }
                if (gradientAngleRow) {
                    gradientAngleRow.style.display = 'none';
                }
                return;
            }

            const isSolid = backgroundTypeSelect.value === 'solid';
            if (solidRow) {
                solidRow.style.display = isSolid ? '' : 'none';
            }
            if (gradientRow) {
                gradientRow.style.display = isSolid ? 'none' : '';
            }
            if (gradientAngleRow) {
                gradientAngleRow.style.display = isSolid ? 'none' : '';
            }
        };

        const updateAdvancedToggleLabel = () => {
            if (!advancedToggleButton) {
                return;
            }

            const showLabel = advancedToggleButton.getAttribute('data-label-show') || 'Advanced';
            const hideLabel = advancedToggleButton.getAttribute('data-label-hide') || 'Hide Advanced';
            advancedToggleButton.textContent = isAdvancedVisible ? hideLabel : showLabel;
            advancedToggleButton.setAttribute('aria-expanded', isAdvancedVisible ? 'true' : 'false');
        };

        const setAdvancedVisibility = (visible) => {
            isAdvancedVisible = visible;

            advancedRows.forEach((row) => {
                row.style.display = isAdvancedVisible ? '' : 'none';
            });

            updateBackgroundRows();
            updateAdvancedToggleLabel();
        };

        const updateAngleLabel = () => {
            if (gradientAngleInput && gradientAngleValue) {
                gradientAngleValue.textContent = `${gradientAngleInput.value}deg`;
            }
        };

        const buildGradientPreview = () => {
            if (!gradientStopsContainer) {
                return '';
            }

            const rows = Array.from(gradientStopsContainer.querySelectorAll('.vep-gradient-stop'));
            const parts = rows.map((row) => {
                const colorInput = row.querySelector('.vep-gradient-color');
                const rangeInput = row.querySelector('.vep-gradient-stop-range');
                const color = colorInput ? colorInput.value : '#000000';
                const stop = rangeInput ? Math.min(100, Math.max(0, parseInt(rangeInput.value, 10) || 0)) : 0;
                return `${color} ${stop}%`;
            });

            const angle = gradientAngleInput ? Math.min(360, Math.max(0, parseInt(gradientAngleInput.value, 10) || 0)) : 135;

            return `linear-gradient(${angle}deg, ${parts.join(', ')})`;
        };

        const updatePreview = () => {
            if (!previewElement) {
                return;
            }

            if (backgroundTypeSelect.value === 'solid') {
                previewElement.style.background = solidColorInput ? solidColorInput.value : '#1e3c72';
                return;
            }

            previewElement.style.background = buildGradientPreview();
        };

        const swapStopValues = (fromRow, toRow) => {
            const fromColor = fromRow.querySelector('.vep-gradient-color');
            const fromRange = fromRow.querySelector('.vep-gradient-stop-range');
            const toColor = toRow.querySelector('.vep-gradient-color');
            const toRange = toRow.querySelector('.vep-gradient-stop-range');

            if (!fromColor || !fromRange || !toColor || !toRange) {
                return;
            }

            const colorTmp = fromColor.value;
            const rangeTmp = fromRange.value;

            fromColor.value = toColor.value;
            fromRange.value = toRange.value;
            toColor.value = colorTmp;
            toRange.value = rangeTmp;
        };

        backgroundTypeSelect.addEventListener('change', () => {
            updateBackgroundRows();
            updatePreview();
        });

        if (solidColorInput) {
            solidColorInput.addEventListener('input', updatePreview);
            solidColorInput.addEventListener('change', updatePreview);
        }

        if (textColorInput) {
            textColorInput.addEventListener('input', updatePreview);
            textColorInput.addEventListener('change', updatePreview);
        }

        if (gradientStopsContainer) {
            gradientStopsContainer.querySelectorAll('.vep-gradient-stop-range').forEach((rangeInput) => {
                rangeInput.addEventListener('input', () => {
                    updateRangeLabels();
                    updatePreview();
                });
                rangeInput.addEventListener('change', () => {
                    updateRangeLabels();
                    updatePreview();
                });
            });

            gradientStopsContainer.querySelectorAll('.vep-gradient-color').forEach((colorInput) => {
                colorInput.addEventListener('input', updatePreview);
                colorInput.addEventListener('change', updatePreview);
            });

            gradientStopsContainer.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                const currentStop = target.closest('.vep-gradient-stop');
                if (!currentStop) {
                    return;
                }

                const allStops = Array.from(gradientStopsContainer.querySelectorAll('.vep-gradient-stop'));
                const index = allStops.indexOf(currentStop);
                if (index === -1) {
                    return;
                }

                if (target.classList.contains('vep-gradient-move-up') && index > 0) {
                    swapStopValues(currentStop, allStops[index - 1]);
                    updateRangeLabels();
                    updatePreview();
                }

                if (target.classList.contains('vep-gradient-move-down') && index < allStops.length - 1) {
                    swapStopValues(currentStop, allStops[index + 1]);
                    updateRangeLabels();
                    updatePreview();
                }
            });
        }

        if (gradientAngleInput) {
            gradientAngleInput.addEventListener('input', () => {
                updateAngleLabel();
                updatePreview();
            });
            gradientAngleInput.addEventListener('change', () => {
                updateAngleLabel();
                updatePreview();
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', () => {
                backgroundTypeSelect.value = resetButton.getAttribute('data-default-type') || 'gradient';

                if (solidColorInput) {
                    solidColorInput.value = resetButton.getAttribute('data-default-solid-color') || '#1e3c72';
                }

                if (textColorInput) {
                    textColorInput.value = resetButton.getAttribute('data-default-text-color') || '#ffffff';
                }

                if (gradientStopsContainer) {
                    const rows = Array.from(gradientStopsContainer.querySelectorAll('.vep-gradient-stop'));

                    const defaultColors = [
                        resetButton.getAttribute('data-default-gradient-color-1') || '#1e3c72',
                        resetButton.getAttribute('data-default-gradient-color-2') || '#2a5298',
                        resetButton.getAttribute('data-default-gradient-color-3') || '#7e22ce'
                    ];

                    const defaultStops = [
                        resetButton.getAttribute('data-default-gradient-stop-1') || '0',
                        resetButton.getAttribute('data-default-gradient-stop-2') || '50',
                        resetButton.getAttribute('data-default-gradient-stop-3') || '100'
                    ];

                    if (gradientAngleInput) {
                        gradientAngleInput.value = resetButton.getAttribute('data-default-gradient-angle') || '135';
                    }

                    rows.forEach((row, index) => {
                        const colorInput = row.querySelector('.vep-gradient-color');
                        const rangeInput = row.querySelector('.vep-gradient-stop-range');

                        if (colorInput && defaultColors[index] !== undefined) {
                            colorInput.value = defaultColors[index];
                        }

                        if (rangeInput && defaultStops[index] !== undefined) {
                            rangeInput.value = defaultStops[index];
                        }
                    });
                }

                updateBackgroundRows();
                updateRangeLabels();
                updateAngleLabel();
                updatePreview();
            });
        }

        if (advancedToggleButton) {
            advancedToggleButton.addEventListener('click', () => {
                setAdvancedVisibility(!isAdvancedVisible);
            });
        }

        setAdvancedVisibility(false);
        updateRangeLabels();
        updateAngleLabel();
        updatePreview();
    }

    function initCompetitionsPage() {
        const page = document.querySelector('.vep-competitions-page');
        if (!page || typeof vepAdmin === 'undefined') {
            return;
        }

        const ajaxUrl = vepAdmin.ajaxUrl;
        const nonce = page.dataset.competitionNonce || vepAdmin.competitionNonce;
        const i18n = vepAdmin.i18n || {};
        const activeGrid = window.jQuery ? window.jQuery('#vep-active-competitions') : null;

        const showError = (message) => {
            window.alert(message || i18n.competitionActionFailed || 'An error occurred. Please try again.');
        };

        const showCardNotice = (sourceElement, message, type = 'success') => {
            const card = sourceElement ? sourceElement.closest('.vep-competition-card') : null;
            if (!card) {
                return;
            }

            const oldNotice = card.querySelector('.vep-competition-toast');
            if (oldNotice) {
                oldNotice.remove();
            }

            const toast = document.createElement('div');
            toast.className = `vep-competition-toast vep-competition-toast-${type}`;
            toast.textContent = message;
            card.appendChild(toast);

            // Trigger transition after insertion.
            window.requestAnimationFrame(() => {
                toast.classList.add('is-visible');
            });

            window.setTimeout(() => {
                toast.classList.remove('is-visible');
                window.setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 220);
            }, 1600);
        };

        const reloadPage = () => {
            window.location.reload();
        };

        const renumberCards = () => {
            const cards = document.querySelectorAll('#vep-active-competitions .vep-competition-card');
            cards.forEach((card, index) => {
                const number = card.querySelector('.vep-competition-number');
                if (number) {
                    number.textContent = String(index + 1);
                }
            });
        };

        const postAction = (action, payload = {}) => {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', nonce);

            Object.entries(payload).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach((item) => formData.append(`${key}[]`, item));
                    return;
                }

                if (value !== undefined && value !== null) {
                    formData.append(key, value);
                }
            });

            return fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then((response) => response.json());
        };

        if (activeGrid && activeGrid.length && typeof activeGrid.sortable === 'function') {
            activeGrid.sortable({
                items: '> .vep-competition-card',
                handle: '.vep-competition-drag-handle',
                placeholder: 'vep-sortable-ghost',
                tolerance: 'pointer',
                helper: function(event, item) {
                    const helper = item.clone();
                    helper.addClass('vep-sortable-helper');
                    helper.css('width', item.outerWidth());
                    return helper;
                },
                forceHelperSize: true,
                forcePlaceholderSize: true,
                start: function(event, ui) {
                    this.classList.add('vep-competitions-grid-sorting');
                    ui.placeholder.height(ui.item.outerHeight());
                    ui.placeholder.width(ui.item.outerWidth());
                },
                stop: function() {
                    this.classList.remove('vep-competitions-grid-sorting');
                },
                update: function() {
                    const order = Array.from(document.querySelectorAll('#vep-active-competitions .vep-competition-card'))
                        .map((card) => card.dataset.competitionId)
                        .filter(Boolean);

                    renumberCards();

                    if (order.length === 0) {
                        return;
                    }

                    postAction('vep_reorder_competitions', { order })
                        .then((data) => {
                            if (!data.success) {
                                showError((data.data && data.data.message) || i18n.reorderCompetitionFailed);
                                reloadPage();
                            }
                        })
                        .catch(() => {
                            showError(i18n.reorderCompetitionFailed);
                            reloadPage();
                        });
                }
            });
        }

        document.querySelectorAll('.vep-toggle-active').forEach((button) => {
            button.addEventListener('click', function() {
                const competitionId = this.dataset.competitionId;
                const actionType = this.dataset.action;

                this.disabled = true;

                postAction('vep_toggle_competition_active', {
                    competition_id: competitionId,
                    action_type: actionType
                })
                    .then((data) => {
                        if (!data.success) {
                            this.disabled = false;
                            showError(data.data && data.data.message);
                            return;
                        }

                        reloadPage();
                    })
                    .catch(() => {
                        this.disabled = false;
                        showError(i18n.competitionActionFailed);
                    });
            });
        });

        document.querySelectorAll('.vep-delete-competition').forEach((button) => {
            button.addEventListener('click', function() {
                if (!window.confirm(i18n.confirmCompetitionDelete || 'Are you sure?')) {
                    return;
                }

                const competitionId = this.dataset.competitionId;
                this.disabled = true;

                postAction('vep_delete_competition', {
                    competition_id: competitionId
                })
                    .then((data) => {
                        if (!data.success) {
                            this.disabled = false;
                            showError(data.data && data.data.message);
                            return;
                        }

                        reloadPage();
                    })
                    .catch(() => {
                        this.disabled = false;
                        showError(i18n.competitionActionFailed);
                    });
            });
        });

        document.querySelectorAll('.vep-reset-winner').forEach((button) => {
            button.addEventListener('click', function() {
                const competitionId = this.dataset.competitionId;
                const buttonNonce = this.dataset.nonce;

                if (!competitionId || !buttonNonce) {
                    showError(i18n.competitionActionFailed);
                    return;
                }

                this.disabled = true;

                const formData = new FormData();
                formData.append('action', 'vep_set_competition_winner');
                formData.append('competition_id', competitionId);
                formData.append('winner_id', '');
                formData.append('nonce', buttonNonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.success) {
                            this.disabled = false;
                            showError((data.data && data.data.message) || i18n.competitionActionFailed);
                            return;
                        }

                        showCardNotice(this, i18n.winnerReset || 'Winner reset successfully', 'success');
                        reloadPage();
                    })
                    .catch(() => {
                        this.disabled = false;
                        showError(i18n.competitionActionFailed);
                    });
            });
        });

        // Handle competition winner selection for custom competitions
        document.querySelectorAll('.vep-competition-winner-select').forEach((select) => {
            select.addEventListener('change', function() {
                const competitionId = this.dataset.competitionId;
                const winnerId = this.value;
                const nonce = this.dataset.nonce;

                const formData = new FormData();
                formData.append('action', 'vep_set_competition_winner');
                formData.append('competition_id', competitionId);
                formData.append('winner_id', winnerId);
                formData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.success) {
                            showError((data.data && data.data.message) || i18n.competitionActionFailed);
                            reloadPage();
                            return;
                        }

                        const successMessage = (data.data && data.data.message) || i18n.winnerSaved || 'Saved.';
                        showCardNotice(this, successMessage, 'success');

                        // Re-initialize Choices if available
                        if (typeof Choices !== 'undefined' && this.choicesInstance) {
                            this.choicesInstance.destroy();
                            this.choicesInstance = new Choices(this, {
                                searchEnabled: true,
                                searchFields: ['label'],
                                searchPlaceholderValue: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.searchPlaceholderValue : 'Search...',
                                itemSelectText: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.itemSelectText : 'Press to select',
                                noResultsText: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.noResultsText : 'No results found',
                                noChoicesText: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.noChoicesText : 'No options available',
                                removeItemButton: false,
                                shouldSort: false
                            });
                        }
                    })
                    .catch(() => {
                        showError(i18n.competitionActionFailed);
                        reloadPage();
                    });
            });
        });

        renumberCards();
    }

    function initAdminChoices() {
        if (typeof Choices === 'undefined') {
            return;
        }

        const i18n = (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n : {};
        const selectElements = document.querySelectorAll('.vep-choices');

        selectElements.forEach((element) => {
            if (element.choicesInstance) {
                return;
            }

            element.choicesInstance = new Choices(element, {
                searchEnabled: true,
                searchFields: ['label'],
                searchPlaceholderValue: i18n.searchPlaceholderValue || 'Search...',
                itemSelectText: i18n.itemSelectText || 'Press to select',
                noResultsText: i18n.noResultsText || 'No results found',
                noChoicesText: i18n.noChoicesText || 'No options available',
                removeItemButton: false,
                shouldSort: false
            });
        });
    }
})();

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize category page functionality
        initializeCategoryPage();
    });

    function initializeCategoryPage() {
        var $form = $('.vep-category-form, .vep-tag-form');
        if ($form.length === 0) {
            return;
        }

        var $iconInput = $('#category_icon, #type_icon, #tag_icon');
        var $iconPreview = $('#icon-preview');
        var $openPickerBtn = $('#open-icon-picker');
        var $clearPickerBtn = $('#clear-icon-picker');

        if ($iconInput.length === 0 || $openPickerBtn.length === 0) {
            return;
        }

        var initialData = $form.serialize();

        // All Font Awesome 7.1.0 SOLID + REGULAR + BRAND icons
        // Verified directly from: https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.1.0/css/all.min.css
        var allIcons = [
            // SOLID ICONS (970)
            's:fa-0', 's:fa-1', 's:fa-2', 's:fa-3', 's:fa-4',
            's:fa-5', 's:fa-6', 's:fa-7', 's:fa-8', 's:fa-9',
            's:fa-a', 's:fa-address-book', 's:fa-address-card', 's:fa-adjust', 's:fa-air-freshener',
            's:fa-align-center', 's:fa-align-justify', 's:fa-align-left', 's:fa-align-right', 's:fa-allergies',
            's:fa-ambulance', 's:fa-anchor', 's:fa-angle-double-down', 's:fa-angle-double-left', 's:fa-angle-double-right',
            's:fa-angle-double-up', 's:fa-angle-down', 's:fa-angle-left', 's:fa-angle-right', 's:fa-angle-up',
            's:fa-angry', 's:fa-ankh', 's:fa-apple-alt', 's:fa-archive', 's:fa-archway',
            's:fa-arrow-alt-circle-down', 's:fa-arrow-alt-circle-left', 's:fa-arrow-alt-circle-right', 's:fa-arrow-alt-circle-up', 's:fa-arrow-circle-down',
            's:fa-arrow-circle-left', 's:fa-arrow-circle-right', 's:fa-arrow-circle-up', 's:fa-arrow-down', 's:fa-arrow-left',
            's:fa-arrow-right', 's:fa-arrow-up', 's:fa-arrows-alt', 's:fa-arrows-alt-h', 's:fa-arrows-alt-v',
            's:fa-assistive-listening-systems', 's:fa-asterisk', 's:fa-at', 's:fa-atlas', 's:fa-atom',
            's:fa-audio-description', 's:fa-award', 's:fa-b', 's:fa-baby', 's:fa-baby-carriage',
            's:fa-backspace', 's:fa-backward', 's:fa-bacon', 's:fa-balance-scale', 's:fa-balance-scale-left',
            's:fa-balance-scale-right', 's:fa-ban', 's:fa-band-aid', 's:fa-barcode', 's:fa-bars',
            's:fa-baseball-ball', 's:fa-basketball-ball', 's:fa-bath', 's:fa-battery-empty', 's:fa-battery-full',
            's:fa-battery-half', 's:fa-battery-quarter', 's:fa-battery-three-quarters', 's:fa-bed', 's:fa-beer',
            's:fa-bell', 's:fa-bell-slash', 's:fa-bezier-curve', 's:fa-bible', 's:fa-bicycle',
            's:fa-biking', 's:fa-binoculars', 's:fa-biohazard', 's:fa-birthday-cake', 's:fa-blender',
            's:fa-blender-phone', 's:fa-blind', 's:fa-blog', 's:fa-bold', 's:fa-bolt',
            's:fa-bomb', 's:fa-bone', 's:fa-bong', 's:fa-book', 's:fa-book-dead',
            's:fa-book-medical', 's:fa-book-open', 's:fa-book-reader', 's:fa-bookmark', 's:fa-border-all',
            's:fa-border-none', 's:fa-border-style', 's:fa-bowling-ball', 's:fa-box', 's:fa-box-open',
            's:fa-boxes', 's:fa-braille', 's:fa-brain', 's:fa-bread-slice', 's:fa-briefcase',
            's:fa-briefcase-medical', 's:fa-broadcast-tower', 's:fa-broom', 's:fa-brush', 's:fa-bug',
            's:fa-building', 's:fa-bullhorn', 's:fa-bullseye', 's:fa-burn', 's:fa-bus',
            's:fa-bus-alt', 's:fa-business-time', 's:fa-calculator', 's:fa-calendar', 's:fa-calendar-alt',
            's:fa-calendar-check', 's:fa-calendar-day', 's:fa-calendar-minus', 's:fa-calendar-plus', 's:fa-calendar-times',
            's:fa-calendar-week', 's:fa-camera', 's:fa-camera-retro', 's:fa-campground', 's:fa-candy-cane',
            's:fa-cannabis', 's:fa-capsules', 's:fa-car', 's:fa-car-alt', 's:fa-car-battery',
            's:fa-car-crash', 's:fa-car-side', 's:fa-caret-down', 's:fa-caret-left', 's:fa-caret-right',
            's:fa-caret-square-down', 's:fa-caret-square-left', 's:fa-caret-square-right', 's:fa-caret-square-up', 's:fa-caret-up',
            's:fa-carrot', 's:fa-cart-arrow-down', 's:fa-cart-plus', 's:fa-cash-register', 's:fa-cat',
            's:fa-certificate', 's:fa-chair', 's:fa-chalkboard', 's:fa-chalkboard-teacher', 's:fa-charging-station',
            's:fa-chart-area', 's:fa-chart-bar', 's:fa-chart-line', 's:fa-chart-pie', 's:fa-check',
            's:fa-check-circle', 's:fa-check-double', 's:fa-check-square', 's:fa-cheese', 's:fa-chess',
            's:fa-chess-bishop', 's:fa-chess-board', 's:fa-chess-king', 's:fa-chess-knight', 's:fa-chess-pawn',
            's:fa-chess-queen', 's:fa-chess-rook', 's:fa-chevron-circle-down', 's:fa-chevron-circle-left', 's:fa-chevron-circle-right',
            's:fa-chevron-circle-up', 's:fa-chevron-down', 's:fa-chevron-left', 's:fa-chevron-right', 's:fa-chevron-up',
            's:fa-child', 's:fa-church', 's:fa-circle', 's:fa-circle-notch', 's:fa-city',
            's:fa-clinic-medical', 's:fa-clipboard', 's:fa-clipboard-check', 's:fa-clipboard-list', 's:fa-clock',
            's:fa-clone', 's:fa-closed-captioning', 's:fa-cloud', 's:fa-cloud-download-alt', 's:fa-cloud-meatball',
            's:fa-cloud-moon', 's:fa-cloud-moon-rain', 's:fa-cloud-rain', 's:fa-cloud-showers-heavy', 's:fa-cloud-sun',
            's:fa-cloud-sun-rain', 's:fa-cloud-upload-alt', 's:fa-cocktail', 's:fa-code', 's:fa-code-branch',
            's:fa-coffee', 's:fa-cog', 's:fa-cogs', 's:fa-coins', 's:fa-columns',
            's:fa-comment', 's:fa-comment-alt', 's:fa-comment-dollar', 's:fa-comment-dots', 's:fa-comment-medical',
            's:fa-comment-slash', 's:fa-comments', 's:fa-comments-dollar', 's:fa-compact-disc', 's:fa-compass',
            's:fa-compress', 's:fa-compress-arrows-alt', 's:fa-concierge-bell', 's:fa-cookie', 's:fa-cookie-bite',
            's:fa-copy', 's:fa-copyright', 's:fa-couch', 's:fa-credit-card', 's:fa-crop',
            's:fa-crop-alt', 's:fa-cross', 's:fa-crosshairs', 's:fa-crow', 's:fa-crown',
            's:fa-crutch', 's:fa-cube', 's:fa-cubes', 's:fa-cut', 's:fa-database',
            's:fa-deaf', 's:fa-democrat', 's:fa-desktop', 's:fa-dharmachakra', 's:fa-diagnoses',
            's:fa-dice', 's:fa-dice-d20', 's:fa-dice-d6', 's:fa-dice-five', 's:fa-dice-four',
            's:fa-dice-one', 's:fa-dice-six', 's:fa-dice-three', 's:fa-dice-two', 's:fa-digital-tachograph',
            's:fa-directions', 's:fa-divide', 's:fa-dizzy', 's:fa-dna', 's:fa-dog',
            's:fa-dollar-sign', 's:fa-dolly', 's:fa-dolly-flatbed', 's:fa-donate', 's:fa-door-closed',
            's:fa-door-open', 's:fa-dot-circle', 's:fa-dove', 's:fa-download', 's:fa-drafting-compass',
            's:fa-dragon', 's:fa-draw-polygon', 's:fa-drum', 's:fa-drum-steelpan', 's:fa-drumstick-bite',
            's:fa-dumbbell', 's:fa-dumpster', 's:fa-dumpster-fire', 's:fa-dungeon', 's:fa-edit',
            's:fa-egg', 's:fa-eject', 's:fa-ellipsis-h', 's:fa-ellipsis-v', 's:fa-envelope',
            's:fa-envelope-open', 's:fa-envelope-open-text', 's:fa-envelope-square', 's:fa-equals', 's:fa-eraser',
            's:fa-ethernet', 's:fa-euro-sign', 's:fa-exchange-alt', 's:fa-exclamation', 's:fa-exclamation-circle',
            's:fa-exclamation-triangle', 's:fa-expand', 's:fa-expand-arrows-alt', 's:fa-external-link-alt', 's:fa-external-link-square-alt',
            's:fa-eye', 's:fa-eye-dropper', 's:fa-eye-slash', 's:fa-fan', 's:fa-fast-backward',
            's:fa-fast-forward', 's:fa-fax', 's:fa-feather', 's:fa-feather-alt', 's:fa-female',
            's:fa-fighter-jet', 's:fa-file', 's:fa-file-alt', 's:fa-file-archive', 's:fa-file-audio',
            's:fa-file-code', 's:fa-file-contract', 's:fa-file-csv', 's:fa-file-download', 's:fa-file-excel',
            's:fa-file-export', 's:fa-file-image', 's:fa-file-import', 's:fa-file-invoice', 's:fa-file-invoice-dollar',
            's:fa-file-medical', 's:fa-file-medical-alt', 's:fa-file-pdf', 's:fa-file-powerpoint', 's:fa-file-prescription',
            's:fa-file-signature', 's:fa-file-upload', 's:fa-file-video', 's:fa-file-word', 's:fa-fill',
            's:fa-fill-drip', 's:fa-film', 's:fa-filter', 's:fa-fingerprint', 's:fa-fire',
            's:fa-fire-alt', 's:fa-fire-extinguisher', 's:fa-first-aid', 's:fa-fish', 's:fa-fist-raised',
            's:fa-flag', 's:fa-flag-checkered', 's:fa-flag-usa', 's:fa-flask', 's:fa-flushed',
            's:fa-folder', 's:fa-folder-minus', 's:fa-folder-open', 's:fa-folder-plus', 's:fa-font',
            's:fa-football-ball', 's:fa-forward', 's:fa-frog', 's:fa-frown', 's:fa-frown-open',
            's:fa-funnel-dollar', 's:fa-futbol', 's:fa-gamepad', 's:fa-gas-pump', 's:fa-gavel',
            's:fa-gem', 's:fa-genderless', 's:fa-ghost', 's:fa-gift', 's:fa-gifts',
            's:fa-glass-cheers', 's:fa-glass-martini', 's:fa-glass-martini-alt', 's:fa-glass-whiskey', 's:fa-glasses',
            's:fa-globe', 's:fa-globe-africa', 's:fa-globe-americas', 's:fa-globe-asia', 's:fa-globe-europe',
            's:fa-golf-ball', 's:fa-gopuram', 's:fa-graduation-cap', 's:fa-greater-than', 's:fa-greater-than-equal',
            's:fa-grimace', 's:fa-grin', 's:fa-grin-alt', 's:fa-grin-beam', 's:fa-grin-beam-sweat',
            's:fa-grin-hearts', 's:fa-grin-squint', 's:fa-grin-squint-tears', 's:fa-grin-stars', 's:fa-grin-tears',
            's:fa-grin-tongue', 's:fa-grin-tongue-squint', 's:fa-grin-tongue-wink', 's:fa-grin-wink', 's:fa-grip-horizontal',
            's:fa-grip-lines', 's:fa-grip-lines-vertical', 's:fa-grip-vertical', 's:fa-guitar', 's:fa-h-square',
            's:fa-hammer', 's:fa-hamsa', 's:fa-hand-holding', 's:fa-hand-holding-heart', 's:fa-hand-holding-usd',
            's:fa-hand-holding-water', 's:fa-hand-lizard', 's:fa-hand-middle-finger', 's:fa-hand-paper', 's:fa-hand-peace',
            's:fa-hand-point-down', 's:fa-hand-point-left', 's:fa-hand-point-right', 's:fa-hand-point-up', 's:fa-hand-pointer',
            's:fa-hand-rock', 's:fa-hand-scissors', 's:fa-hand-spock', 's:fa-hands', 's:fa-hands-helping',
            's:fa-handshake', 's:fa-handshake-slash', 's:fa-hanukiah', 's:fa-hard-hat', 's:fa-hashtag',
            's:fa-hat-cowboy', 's:fa-hat-cowboy-side', 's:fa-hat-wizard', 's:fa-haykal', 's:fa-hdd',
            's:fa-heading', 's:fa-headphones', 's:fa-headphones-alt', 's:fa-headset', 's:fa-heart',
            's:fa-heart-broken', 's:fa-heartbeat', 's:fa-helicopter', 's:fa-highlighter', 's:fa-hiking',
            's:fa-hippo', 's:fa-history', 's:fa-hockey-puck', 's:fa-holly-berry', 's:fa-home',
            's:fa-horse', 's:fa-horse-head', 's:fa-hospital', 's:fa-hospital-alt', 's:fa-hospital-symbol',
            's:fa-hot-tub', 's:fa-hotdog', 's:fa-hotel', 's:fa-hourglass', 's:fa-hourglass-end',
            's:fa-hourglass-half', 's:fa-hourglass-start', 's:fa-house-damage', 's:fa-hryvnia', 's:fa-i-cursor',
            's:fa-ice-cream', 's:fa-icicles', 's:fa-icons', 's:fa-id-badge', 's:fa-id-card',
            's:fa-id-card-alt', 's:fa-igloo', 's:fa-image', 's:fa-images', 's:fa-inbox',
            's:fa-indent', 's:fa-industry', 's:fa-infinity', 's:fa-info', 's:fa-info-circle',
            's:fa-italic', 's:fa-jedi', 's:fa-joint', 's:fa-journal-whills', 's:fa-kaaba',
            's:fa-key', 's:fa-keyboard', 's:fa-khanda', 's:fa-kiss', 's:fa-kiss-beam',
            's:fa-kiss-wink-heart', 's:fa-kiwi-bird', 's:fa-landmark', 's:fa-language', 's:fa-laptop',
            's:fa-laptop-code', 's:fa-laptop-medical', 's:fa-laugh', 's:fa-laugh-beam', 's:fa-laugh-squint',
            's:fa-laugh-wink', 's:fa-layer-group', 's:fa-leaf', 's:fa-lemon', 's:fa-less-than',
            's:fa-less-than-equal', 's:fa-level-down-alt', 's:fa-level-up-alt', 's:fa-life-ring', 's:fa-lightbulb',
            's:fa-link', 's:fa-lira-sign', 's:fa-list', 's:fa-list-alt', 's:fa-list-ol',
            's:fa-list-ul', 's:fa-location-arrow', 's:fa-lock', 's:fa-lock-open', 's:fa-long-arrow-alt-down',
            's:fa-long-arrow-alt-left', 's:fa-long-arrow-alt-right', 's:fa-long-arrow-alt-up', 's:fa-low-vision', 's:fa-luggage-cart',
            's:fa-magic', 's:fa-magnet', 's:fa-mail-bulk', 's:fa-male', 's:fa-map',
            's:fa-map-marked', 's:fa-map-marked-alt', 's:fa-map-marker', 's:fa-map-marker-alt', 's:fa-map-pin',
            's:fa-map-signs', 's:fa-marker', 's:fa-mars', 's:fa-mars-double', 's:fa-mars-stroke',
            's:fa-mars-stroke-h', 's:fa-mars-stroke-v', 's:fa-mask', 's:fa-medal', 's:fa-medkit',
            's:fa-meh', 's:fa-meh-blank', 's:fa-meh-rolling-eyes', 's:fa-memory', 's:fa-menorah',
            's:fa-mercury', 's:fa-meteor', 's:fa-microchip', 's:fa-microphone', 's:fa-microphone-alt',
            's:fa-microphone-alt-slash', 's:fa-microphone-slash', 's:fa-microscope', 's:fa-minus', 's:fa-minus-circle',
            's:fa-minus-square', 's:fa-mitten', 's:fa-mobile', 's:fa-mobile-alt', 's:fa-money-bill',
            's:fa-money-bill-alt', 's:fa-money-bill-wave', 's:fa-money-bill-wave-alt', 's:fa-money-check', 's:fa-money-check-alt',
            's:fa-monument', 's:fa-moon', 's:fa-mortar-pestle', 's:fa-mosque', 's:fa-motorcycle',
            's:fa-mountain', 's:fa-mouse-pointer', 's:fa-mug-hot', 's:fa-music', 's:fa-network-wired',
            's:fa-neuter', 's:fa-newspaper', 's:fa-not-equal', 's:fa-notes-medical', 's:fa-object-group',
            's:fa-object-ungroup', 's:fa-oil-can', 's:fa-om', 's:fa-otter', 's:fa-outdent',
            's:fa-pager', 's:fa-paint-brush', 's:fa-paint-roller', 's:fa-palette', 's:fa-pallet',
            's:fa-paper-plane', 's:fa-paperclip', 's:fa-parachute-box', 's:fa-paragraph', 's:fa-parking',
            's:fa-passport', 's:fa-pastafarianism', 's:fa-paste', 's:fa-pause', 's:fa-pause-circle',
            's:fa-paw', 's:fa-peace', 's:fa-pen', 's:fa-pen-alt', 's:fa-pen-fancy',
            's:fa-pen-nib', 's:fa-pen-square', 's:fa-pencil-alt', 's:fa-pencil-ruler', 's:fa-people-carry',
            's:fa-pepper-hot', 's:fa-percent', 's:fa-percentage', 's:fa-person-booth', 's:fa-phone',
            's:fa-phone-alt', 's:fa-phone-slash', 's:fa-phone-square', 's:fa-phone-square-alt', 's:fa-phone-volume',
            's:fa-photo-video', 's:fa-piggy-bank', 's:fa-pills', 's:fa-pizza-slice', 's:fa-place-of-worship',
            's:fa-plane', 's:fa-plane-arrival', 's:fa-plane-departure', 's:fa-play', 's:fa-play-circle',
            's:fa-plug', 's:fa-plus', 's:fa-plus-circle', 's:fa-plus-square', 's:fa-podcast',
            's:fa-poll', 's:fa-poll-h', 's:fa-poo', 's:fa-poo-storm', 's:fa-poop',
            's:fa-portrait', 's:fa-pound-sign', 's:fa-power-off', 's:fa-pray', 's:fa-praying-hands',
            's:fa-prescription', 's:fa-prescription-bottle', 's:fa-prescription-bottle-alt', 's:fa-print', 's:fa-procedures',
            's:fa-project-diagram', 's:fa-puzzle-piece', 's:fa-qrcode', 's:fa-question', 's:fa-question-circle',
            's:fa-quidditch', 's:fa-quote-left', 's:fa-quote-right', 's:fa-quran', 's:fa-radiation',
            's:fa-radiation-alt', 's:fa-rainbow', 's:fa-random', 's:fa-receipt', 's:fa-record-vinyl',
            's:fa-recycle', 's:fa-redo', 's:fa-redo-alt', 's:fa-registered', 's:fa-remove-format',
            's:fa-reply', 's:fa-reply-all', 's:fa-republican', 's:fa-restroom', 's:fa-retweet',
            's:fa-ribbon', 's:fa-ring', 's:fa-road', 's:fa-robot', 's:fa-rocket',
            's:fa-route', 's:fa-rss', 's:fa-rss-square', 's:fa-ruble-sign', 's:fa-ruler',
            's:fa-ruler-combined', 's:fa-ruler-horizontal', 's:fa-ruler-vertical', 's:fa-running', 's:fa-rupee-sign',
            's:fa-sad-cry', 's:fa-sad-tear', 's:fa-satellite', 's:fa-satellite-dish', 's:fa-save',
            's:fa-school', 's:fa-screwdriver', 's:fa-scroll', 's:fa-sd-card', 's:fa-search',
            's:fa-search-dollar', 's:fa-search-location', 's:fa-search-minus', 's:fa-search-plus', 's:fa-seedling',
            's:fa-server', 's:fa-shapes', 's:fa-share', 's:fa-share-alt', 's:fa-share-alt-square',
            's:fa-share-square', 's:fa-shekel-sign', 's:fa-shield-alt', 's:fa-ship', 's:fa-shipping-fast',
            's:fa-shoe-prints', 's:fa-shopping-bag', 's:fa-shopping-basket', 's:fa-shopping-cart', 's:fa-shower',
            's:fa-shuttle-van', 's:fa-sign', 's:fa-sign-in-alt', 's:fa-sign-language', 's:fa-sign-out-alt',
            's:fa-signal', 's:fa-signature', 's:fa-sim-card', 's:fa-sitemap', 's:fa-skating',
            's:fa-skiing', 's:fa-skiing-nordic', 's:fa-skull', 's:fa-skull-crossbones', 's:fa-slash',
            's:fa-sleigh', 's:fa-sliders-h', 's:fa-smile', 's:fa-smile-beam', 's:fa-smile-wink',
            's:fa-smog', 's:fa-smoking', 's:fa-smoking-ban', 's:fa-sms', 's:fa-snowboarding',
            's:fa-snowflake', 's:fa-snowman', 's:fa-snowplow', 's:fa-socks', 's:fa-solar-panel',
            's:fa-sort', 's:fa-sort-alpha-down', 's:fa-sort-alpha-down-alt', 's:fa-sort-alpha-up', 's:fa-sort-alpha-up-alt',
            's:fa-sort-amount-down', 's:fa-sort-amount-down-alt', 's:fa-sort-amount-up', 's:fa-sort-amount-up-alt', 's:fa-sort-down',
            's:fa-sort-numeric-down', 's:fa-sort-numeric-down-alt', 's:fa-sort-numeric-up', 's:fa-sort-numeric-up-alt', 's:fa-sort-up',
            's:fa-spa', 's:fa-space-shuttle', 's:fa-spell-check', 's:fa-spider', 's:fa-spinner',
            's:fa-splotch', 's:fa-spray-can', 's:fa-square', 's:fa-square-full', 's:fa-square-root-alt',
            's:fa-stamp', 's:fa-star', 's:fa-star-and-crescent', 's:fa-star-half', 's:fa-star-half-alt',
            's:fa-star-of-david', 's:fa-star-of-life', 's:fa-step-backward', 's:fa-step-forward', 's:fa-stethoscope',
            's:fa-sticky-note', 's:fa-stop', 's:fa-stop-circle', 's:fa-stopwatch', 's:fa-store',
            's:fa-store-alt', 's:fa-stream', 's:fa-street-view', 's:fa-strikethrough', 's:fa-stroopwafel',
            's:fa-subscript', 's:fa-subway', 's:fa-suitcase', 's:fa-suitcase-rolling', 's:fa-sun',
            's:fa-superscript', 's:fa-surprise', 's:fa-swatchbook', 's:fa-swimmer', 's:fa-swimming-pool',
            's:fa-synagogue', 's:fa-sync', 's:fa-sync-alt', 's:fa-syringe', 's:fa-table',
            's:fa-table-tennis', 's:fa-tablet', 's:fa-tablet-alt', 's:fa-tablets', 's:fa-tachometer-alt',
            's:fa-tag', 's:fa-tags', 's:fa-tape', 's:fa-tasks', 's:fa-taxi',
            's:fa-teeth', 's:fa-teeth-open', 's:fa-temperature-high', 's:fa-temperature-low', 's:fa-tenge',
            's:fa-terminal', 's:fa-text-height', 's:fa-text-width', 's:fa-th', 's:fa-th-large',
            's:fa-th-list', 's:fa-theater-masks', 's:fa-thermometer', 's:fa-thermometer-empty', 's:fa-thermometer-full',
            's:fa-thermometer-half', 's:fa-thermometer-quarter', 's:fa-thermometer-three-quarters', 's:fa-thumbs-down', 's:fa-thumbs-up',
            's:fa-thumbtack', 's:fa-ticket-alt', 's:fa-times', 's:fa-times-circle', 's:fa-tint',
            's:fa-tint-slash', 's:fa-tired', 's:fa-toggle-off', 's:fa-toggle-on', 's:fa-toilet',
            's:fa-toilet-paper', 's:fa-toolbox', 's:fa-tools', 's:fa-tooth', 's:fa-torah',
            's:fa-torii-gate', 's:fa-tractor', 's:fa-trademark', 's:fa-traffic-light', 's:fa-train',
            's:fa-tram', 's:fa-transgender', 's:fa-transgender-alt', 's:fa-trash', 's:fa-trash-alt',
            's:fa-trash-restore', 's:fa-trash-restore-alt', 's:fa-tree', 's:fa-trophy', 's:fa-truck',
            's:fa-truck-loading', 's:fa-truck-monster', 's:fa-truck-moving', 's:fa-truck-pickup', 's:fa-tshirt',
            's:fa-tty', 's:fa-tv', 's:fa-umbrella', 's:fa-umbrella-beach', 's:fa-underline',
            's:fa-undo', 's:fa-undo-alt', 's:fa-universal-access', 's:fa-university', 's:fa-unlink',
            's:fa-unlock', 's:fa-unlock-alt', 's:fa-upload', 's:fa-user', 's:fa-user-alt',
            's:fa-user-alt-slash', 's:fa-user-astronaut', 's:fa-user-check', 's:fa-user-circle', 's:fa-user-clock',
            's:fa-user-cog', 's:fa-user-edit', 's:fa-user-friends', 's:fa-user-graduate', 's:fa-user-injured',
            's:fa-user-lock', 's:fa-user-md', 's:fa-user-minus', 's:fa-user-ninja', 's:fa-user-nurse',
            's:fa-user-plus', 's:fa-user-secret', 's:fa-user-shield', 's:fa-user-slash', 's:fa-user-tag',
            's:fa-user-tie', 's:fa-user-times', 's:fa-users', 's:fa-users-cog', 's:fa-utensil-spoon',
            's:fa-utensils', 's:fa-vector-square', 's:fa-venus', 's:fa-venus-double', 's:fa-venus-mars',
            's:fa-vial', 's:fa-vials', 's:fa-video', 's:fa-video-slash', 's:fa-vihara',
            's:fa-voicemail', 's:fa-volleyball-ball', 's:fa-volume-down', 's:fa-volume-mute', 's:fa-volume-off',
            's:fa-volume-up', 's:fa-vote-yea', 's:fa-vr-cardboard', 's:fa-walking', 's:fa-wallet',
            's:fa-warehouse', 's:fa-water', 's:fa-wave-square', 's:fa-weight', 's:fa-weight-hanging',
            's:fa-wheelchair', 's:fa-wifi', 's:fa-wind', 's:fa-window-close', 's:fa-window-maximize',
            's:fa-window-minimize', 's:fa-window-restore', 's:fa-wine-bottle', 's:fa-wine-glass', 's:fa-wine-glass-alt',
            's:fa-won-sign', 's:fa-wrench', 's:fa-x-ray', 's:fa-yen-sign', 's:fa-yin-yang',

            // REGULAR ICONS (152)
            'r:fa-address-book', 'r:fa-address-card', 'r:fa-angry', 'r:fa-arrow-alt-circle-down', 'r:fa-arrow-alt-circle-left',
            'r:fa-arrow-alt-circle-right', 'r:fa-arrow-alt-circle-up', 'r:fa-bell', 'r:fa-bell-slash', 'r:fa-bookmark',
            'r:fa-building', 'r:fa-calendar', 'r:fa-calendar-alt', 'r:fa-calendar-check', 'r:fa-calendar-minus',
            'r:fa-calendar-plus', 'r:fa-calendar-times', 'r:fa-caret-square-down', 'r:fa-caret-square-left', 'r:fa-caret-square-right',
            'r:fa-caret-square-up', 'r:fa-chart-bar', 'r:fa-check-circle', 'r:fa-check-square', 'r:fa-circle',
            'r:fa-clipboard', 'r:fa-clock', 'r:fa-clone', 'r:fa-closed-captioning', 'r:fa-comment',
            'r:fa-comment-alt', 'r:fa-comment-dots', 'r:fa-comments', 'r:fa-compass', 'r:fa-copy',
            'r:fa-copyright', 'r:fa-credit-card', 'r:fa-dizzy', 'r:fa-dot-circle', 'r:fa-edit',
            'r:fa-envelope', 'r:fa-envelope-open', 'r:fa-eye', 'r:fa-eye-slash', 'r:fa-file',
            'r:fa-file-alt', 'r:fa-file-archive', 'r:fa-file-audio', 'r:fa-file-code', 'r:fa-file-excel',
            'r:fa-file-image', 'r:fa-file-pdf', 'r:fa-file-powerpoint', 'r:fa-file-video', 'r:fa-file-word',
            'r:fa-flag', 'r:fa-flushed', 'r:fa-folder', 'r:fa-folder-open', 'r:fa-font-awesome-logo-full',
            'r:fa-frown', 'r:fa-frown-open', 'r:fa-futbol', 'r:fa-gem', 'r:fa-grimace',
            'r:fa-grin', 'r:fa-grin-alt', 'r:fa-grin-beam', 'r:fa-grin-beam-sweat', 'r:fa-grin-hearts',
            'r:fa-grin-squint', 'r:fa-grin-squint-tears', 'r:fa-grin-stars', 'r:fa-grin-tears', 'r:fa-grin-tongue',
            'r:fa-grin-tongue-squint', 'r:fa-grin-tongue-wink', 'r:fa-grin-wink', 'r:fa-hand-lizard', 'r:fa-hand-paper',
            'r:fa-hand-peace', 'r:fa-hand-point-down', 'r:fa-hand-point-left', 'r:fa-hand-point-right', 'r:fa-hand-point-up',
            'r:fa-hand-pointer', 'r:fa-hand-rock', 'r:fa-hand-scissors', 'r:fa-hand-spock', 'r:fa-handshake',
            'r:fa-hdd', 'r:fa-heart', 'r:fa-hospital', 'r:fa-hourglass', 'r:fa-id-badge',
            'r:fa-id-card', 'r:fa-image', 'r:fa-images', 'r:fa-keyboard', 'r:fa-kiss',
            'r:fa-kiss-beam', 'r:fa-kiss-wink-heart', 'r:fa-laugh', 'r:fa-laugh-beam', 'r:fa-laugh-squint',
            'r:fa-laugh-wink', 'r:fa-lemon', 'r:fa-life-ring', 'r:fa-lightbulb', 'r:fa-list-alt',
            'r:fa-map', 'r:fa-meh', 'r:fa-meh-blank', 'r:fa-meh-rolling-eyes', 'r:fa-minus-square',
            'r:fa-money-bill-alt', 'r:fa-moon', 'r:fa-newspaper', 'r:fa-object-group', 'r:fa-object-ungroup',
            'r:fa-paper-plane', 'r:fa-pause-circle', 'r:fa-play-circle', 'r:fa-plus-square', 'r:fa-question-circle',
            'r:fa-registered', 'r:fa-sad-cry', 'r:fa-sad-tear', 'r:fa-save', 'r:fa-share-square',
            'r:fa-smile', 'r:fa-smile-beam', 'r:fa-smile-wink', 'r:fa-snowflake', 'r:fa-square',
            'r:fa-star', 'r:fa-star-half', 'r:fa-sticky-note', 'r:fa-stop-circle', 'r:fa-sun',
            'r:fa-surprise', 'r:fa-thumbs-down', 'r:fa-thumbs-up', 'r:fa-times-circle', 'r:fa-tired',
            'r:fa-trash-alt', 'r:fa-user', 'r:fa-user-circle', 'r:fa-window-close', 'r:fa-window-maximize',
            'r:fa-window-minimize', 'r:fa-window-restore',

            // BRAND ICONS (446)
            'b:fa-500px', 'b:fa-accessible-icon', 'b:fa-accusoft', 'b:fa-adn', 'b:fa-adobe',
            'b:fa-adversal', 'b:fa-affiliatetheme', 'b:fa-airbnb', 'b:fa-algolia', 'b:fa-alipay',
            'b:fa-amazon', 'b:fa-amazon-pay', 'b:fa-amilia', 'b:fa-android', 'b:fa-angellist',
            'b:fa-angrycreative', 'b:fa-angular', 'b:fa-app-store', 'b:fa-app-store-ios', 'b:fa-apper',
            'b:fa-apple', 'b:fa-apple-pay', 'b:fa-artstation', 'b:fa-asymmetrik', 'b:fa-atlassian',
            'b:fa-audible', 'b:fa-autoprefixer', 'b:fa-avianex', 'b:fa-aviato', 'b:fa-aws',
            'b:fa-bandcamp', 'b:fa-battle-net', 'b:fa-behance', 'b:fa-behance-square', 'b:fa-bimobject',
            'b:fa-bitbucket', 'b:fa-bitcoin', 'b:fa-bity', 'b:fa-black-tie', 'b:fa-blackberry',
            'b:fa-blogger', 'b:fa-blogger-b', 'b:fa-bluetooth', 'b:fa-bluetooth-b', 'b:fa-bootstrap',
            'b:fa-btc', 'b:fa-buffer', 'b:fa-buromobelexperte', 'b:fa-buy-n-large', 'b:fa-buysellads',
            'b:fa-canadian-maple-leaf', 'b:fa-cc-amazon-pay', 'b:fa-cc-amex', 'b:fa-cc-apple-pay', 'b:fa-cc-diners-club',
            'b:fa-cc-discover', 'b:fa-cc-jcb', 'b:fa-cc-mastercard', 'b:fa-cc-paypal', 'b:fa-cc-stripe',
            'b:fa-cc-visa', 'b:fa-centercode', 'b:fa-centos', 'b:fa-chrome', 'b:fa-chromecast',
            'b:fa-cloudscale', 'b:fa-cloudsmith', 'b:fa-cloudversify', 'b:fa-codepen', 'b:fa-codiepie',
            'b:fa-confluence', 'b:fa-connectdevelop', 'b:fa-contao', 'b:fa-cotton-bureau', 'b:fa-cpanel',
            'b:fa-creative-commons', 'b:fa-creative-commons-by', 'b:fa-creative-commons-nc', 'b:fa-creative-commons-nc-eu', 'b:fa-creative-commons-nc-jp',
            'b:fa-creative-commons-nd', 'b:fa-creative-commons-pd', 'b:fa-creative-commons-pd-alt', 'b:fa-creative-commons-remix', 'b:fa-creative-commons-sa',
            'b:fa-creative-commons-sampling', 'b:fa-creative-commons-sampling-plus', 'b:fa-creative-commons-share', 'b:fa-creative-commons-zero', 'b:fa-critical-role',
            'b:fa-css3', 'b:fa-css3-alt', 'b:fa-cuttlefish', 'b:fa-d-and-d', 'b:fa-d-and-d-beyond',
            'b:fa-dailymotion', 'b:fa-dashcube', 'b:fa-deezer', 'b:fa-delicious', 'b:fa-deploydog',
            'b:fa-deskpro', 'b:fa-dev', 'b:fa-deviantart', 'b:fa-dhl', 'b:fa-diaspora',
            'b:fa-digg', 'b:fa-digital-ocean', 'b:fa-discord', 'b:fa-discourse', 'b:fa-dochub',
            'b:fa-docker', 'b:fa-draft2digital', 'b:fa-dribbble', 'b:fa-dribbble-square', 'b:fa-dropbox',
            'b:fa-drupal', 'b:fa-dyalog', 'b:fa-earlybirds', 'b:fa-ebay', 'b:fa-edge',
            'b:fa-elementor', 'b:fa-ello', 'b:fa-ember', 'b:fa-empire', 'b:fa-envira',
            'b:fa-erlang', 'b:fa-ethereum', 'b:fa-etsy', 'b:fa-evernote', 'b:fa-expeditedssl',
            'b:fa-facebook', 'b:fa-facebook-f', 'b:fa-facebook-messenger', 'b:fa-facebook-square', 'b:fa-fantasy-flight-games',
            'b:fa-fedex', 'b:fa-fedora', 'b:fa-figma', 'b:fa-firefox', 'b:fa-firefox-browser',
            'b:fa-first-order', 'b:fa-first-order-alt', 'b:fa-firstdraft', 'b:fa-flickr', 'b:fa-flipboard',
            'b:fa-fly', 'b:fa-font-awesome', 'b:fa-font-awesome-alt', 'b:fa-font-awesome-flag', 'b:fa-fonticons',
            'b:fa-fonticons-fi', 'b:fa-fort-awesome', 'b:fa-fort-awesome-alt', 'b:fa-forumbee', 'b:fa-foursquare',
            'b:fa-free-code-camp', 'b:fa-freebsd', 'b:fa-fulcrum', 'b:fa-galactic-republic', 'b:fa-galactic-senate',
            'b:fa-get-pocket', 'b:fa-gg', 'b:fa-gg-circle', 'b:fa-git', 'b:fa-git-alt',
            'b:fa-git-square', 'b:fa-github', 'b:fa-github-alt', 'b:fa-github-square', 'b:fa-gitkraken',
            'b:fa-gitlab', 'b:fa-gitter', 'b:fa-glide', 'b:fa-glide-g', 'b:fa-gofore',
            'b:fa-goodreads', 'b:fa-goodreads-g', 'b:fa-google', 'b:fa-google-drive', 'b:fa-google-pay',
            'b:fa-google-play', 'b:fa-google-plus', 'b:fa-google-plus-g', 'b:fa-google-plus-square', 'b:fa-google-wallet',
            'b:fa-gratipay', 'b:fa-grav', 'b:fa-gripfire', 'b:fa-grunt', 'b:fa-gulp',
            'b:fa-hacker-news', 'b:fa-hacker-news-square', 'b:fa-hackerrank', 'b:fa-hips', 'b:fa-hire-a-helper',
            'b:fa-hooli', 'b:fa-hornbill', 'b:fa-hotjar', 'b:fa-houzz', 'b:fa-html5',
            'b:fa-hubspot', 'b:fa-ideal', 'b:fa-imdb', 'b:fa-instagram', 'b:fa-instagram-square',
            'b:fa-intercom', 'b:fa-internet-explorer', 'b:fa-invision', 'b:fa-ioxhost', 'b:fa-itch-io',
            'b:fa-itunes', 'b:fa-itunes-note', 'b:fa-java', 'b:fa-jedi-order', 'b:fa-jenkins',
            'b:fa-jira', 'b:fa-joget', 'b:fa-joomla', 'b:fa-js', 'b:fa-js-square',
            'b:fa-jsfiddle', 'b:fa-kaggle', 'b:fa-keybase', 'b:fa-keycdn', 'b:fa-kickstarter',
            'b:fa-kickstarter-k', 'b:fa-korvue', 'b:fa-laravel', 'b:fa-lastfm', 'b:fa-lastfm-square',
            'b:fa-leanpub', 'b:fa-less', 'b:fa-line', 'b:fa-linkedin', 'b:fa-linkedin-in',
            'b:fa-linode', 'b:fa-linux', 'b:fa-lyft', 'b:fa-magento', 'b:fa-mailchimp',
            'b:fa-mandalorian', 'b:fa-markdown', 'b:fa-mastodon', 'b:fa-maxcdn', 'b:fa-mdb',
            'b:fa-medapps', 'b:fa-medium', 'b:fa-medium-m', 'b:fa-medrt', 'b:fa-meetup',
            'b:fa-megaport', 'b:fa-mendeley', 'b:fa-microblog', 'b:fa-microsoft', 'b:fa-mix',
            'b:fa-mixer', 'b:fa-mixcloud', 'b:fa-mizuni', 'b:fa-modx', 'b:fa-monero',
            'b:fa-napster', 'b:fa-neos', 'b:fa-nimblr', 'b:fa-node', 'b:fa-node-js',
            'b:fa-npm', 'b:fa-ns8', 'b:fa-nutritionix', 'b:fa-odnoklassniki', 'b:fa-odnoklassniki-square',
            'b:fa-old-republic', 'b:fa-opencart', 'b:fa-openid', 'b:fa-opera', 'b:fa-optin-monster',
            'b:fa-orcid', 'b:fa-osi', 'b:fa-page4', 'b:fa-pagelines', 'b:fa-palfed',
            'b:fa-patreon', 'b:fa-paypal', 'b:fa-penny-arcade', 'b:fa-periscope', 'b:fa-phabricator',
            'b:fa-phoenix-framework', 'b:fa-phoenix-squadron', 'b:fa-php', 'b:fa-pied-piper', 'b:fa-pied-piper-alt',
            'b:fa-pied-piper-hat', 'b:fa-pied-piper-pp', 'b:fa-pied-piper-square', 'b:fa-pinterest', 'b:fa-pinterest-p',
            'b:fa-pinterest-square', 'b:fa-playstation', 'b:fa-product-hunt', 'b:fa-pushed', 'b:fa-python',
            'b:fa-qq', 'b:fa-quinscape', 'b:fa-quora', 'b:fa-r-project', 'b:fa-raspberry-pi',
            'b:fa-ravelry', 'b:fa-react', 'b:fa-reacteurope', 'b:fa-readme', 'b:fa-rebel',
            'b:fa-red-river', 'b:fa-reddit', 'b:fa-reddit-alien', 'b:fa-reddit-square', 'b:fa-redhat',
            'b:fa-renren', 'b:fa-replyd', 'b:fa-researchgate', 'b:fa-resolving', 'b:fa-rev',
            'b:fa-rocketchat', 'b:fa-rockrms', 'b:fa-rust', 'b:fa-safari', 'b:fa-salesforce',
            'b:fa-sass', 'b:fa-schlix', 'b:fa-scribd', 'b:fa-searchengin', 'b:fa-sellcast',
            'b:fa-sellsy', 'b:fa-servicestack', 'b:fa-shirtsinbulk', 'b:fa-shopify', 'b:fa-shopware',
            'b:fa-simplybuilt', 'b:fa-sistrix', 'b:fa-sith', 'b:fa-sketch', 'b:fa-skyatlas',
            'b:fa-skype', 'b:fa-slack', 'b:fa-slack-hash', 'b:fa-slideshare', 'b:fa-snapchat',
            'b:fa-snapchat-ghost', 'b:fa-snapchat-square', 'b:fa-soundcloud', 'b:fa-sourcetree', 'b:fa-speakap',
            'b:fa-speaker-deck', 'b:fa-spotify', 'b:fa-squarespace', 'b:fa-stack-exchange', 'b:fa-stack-overflow',
            'b:fa-stackpath', 'b:fa-staylinked', 'b:fa-steam', 'b:fa-steam-square', 'b:fa-steam-symbol',
            'b:fa-sticker-mule', 'b:fa-strava', 'b:fa-stripe', 'b:fa-stripe-s', 'b:fa-studiovinari',
            'b:fa-stumbleupon', 'b:fa-stumbleupon-circle', 'b:fa-superpowers', 'b:fa-supple', 'b:fa-suse',
            'b:fa-swift', 'b:fa-symfony', 'b:fa-teamspeak', 'b:fa-telegram', 'b:fa-telegram-plane',
            'b:fa-tencent-weibo', 'b:fa-the-red-yeti', 'b:fa-themeco', 'b:fa-themeisle', 'b:fa-think-peaks',
            'b:fa-tiktok', 'b:fa-trade-federation', 'b:fa-trello', 'b:fa-tripadvisor', 'b:fa-tumblr',
            'b:fa-tumblr-square', 'b:fa-twitch', 'b:fa-twitter', 'b:fa-twitter-square', 'b:fa-typo3',
            'b:fa-uber', 'b:fa-ubuntu', 'b:fa-uikit', 'b:fa-umbraco', 'b:fa-uniregistry',
            'b:fa-unity', 'b:fa-unsplash', 'b:fa-untappd', 'b:fa-ups', 'b:fa-usb',
            'b:fa-usps', 'b:fa-ussunnah', 'b:fa-vaadin', 'b:fa-viacoin', 'b:fa-viadeo',
            'b:fa-viadeo-square', 'b:fa-viber', 'b:fa-vimeo', 'b:fa-vimeo-square', 'b:fa-vimeo-v',
            'b:fa-vine', 'b:fa-vk', 'b:fa-vnv', 'b:fa-vuejs', 'b:fa-waze',
            'b:fa-weebly', 'b:fa-weibo', 'b:fa-weixin', 'b:fa-whatsapp', 'b:fa-whatsapp-square',
            'b:fa-whmcs', 'b:fa-wikipedia-w', 'b:fa-windows', 'b:fa-wix', 'b:fa-wizards-of-the-coast',
            'b:fa-wolf-pack-battalion', 'b:fa-wordpress', 'b:fa-wordpress-simple', 'b:fa-wpbeginner', 'b:fa-wpexplorer',
            'b:fa-wpforms', 'b:fa-wpressr', 'b:fa-xbox', 'b:fa-xing', 'b:fa-xing-square',
            'b:fa-y-combinator', 'b:fa-yahoo', 'b:fa-yammer', 'b:fa-yandex', 'b:fa-yandex-international',
            'b:fa-yarn', 'b:fa-yelp', 'b:fa-yoast', 'b:fa-youtube', 'b:fa-youtube-square',
            'b:fa-zhihu'
        ];

        function parseIconPrefix(iconValue) {
            if (!iconValue) {
                return { class: 'fa-solid', name: '' };
            }

            if (iconValue.indexOf('s:') === 0) {
                return { class: 'fa-solid', name: iconValue.substring(2) };
            } else if (iconValue.indexOf('r:') === 0) {
                return { class: 'fa-regular', name: iconValue.substring(2) };
            } else if (iconValue.indexOf('b:') === 0) {
                return { class: 'fa-brands', name: iconValue.substring(2) };
            }

            return { class: 'fa-solid', name: iconValue };
        }

        var iconsPerPage = 200;
        var currentPage = 0;
        var filteredIcons = allIcons.slice();

        $openPickerBtn.on('click', function() {
            currentPage = 0;
            filteredIcons = allIcons.slice();

            var modal = $('<div class="vep-icon-modal-backdrop"><div class="vep-icon-modal"><div class="vep-icon-modal-header"><h2>Select an Icon (' + allIcons.length + ' available)</h2><button class="vep-icon-modal-close">&times;</button></div><div><input type="text" id="icon-search" placeholder="Search icons..."><div class="vep-icon-grid"></div></div><div class="vep-icon-loader" style="display: none; text-align: center; padding: 20px;"><p>Loading more icons...</p></div></div></div>');
            var modalContent = modal.find('.vep-icon-modal');
            var iconGrid = modalContent.find('.vep-icon-grid');
            var loader = modalContent.find('.vep-icon-loader');

            function loadMoreIcons() {
                var start = currentPage * iconsPerPage;
                var end = start + iconsPerPage;
                var iconsToLoad = filteredIcons.slice(start, end);

                $.each(iconsToLoad, function(index, icon) {
                    var parsed = parseIconPrefix(icon);
                    var iconLabel = parsed.name.replace(/-/g, ' ').substring(3);
                    var iconItem = $('<div class="vep-icon-item" data-icon="' + icon + '" title="' + iconLabel + '"><i class="' + parsed.class + ' ' + parsed.name + '"></i><span>' + iconLabel + '</span></div>');
                    iconGrid.append(iconItem);
                });

                currentPage++;

                if (start + iconsPerPage >= filteredIcons.length) {
                    loader.hide();
                } else {
                    loader.show();
                }
            }

            function closeModal() {
                modal.remove();
            }

            $('body').append(modal);
            loadMoreIcons();

            iconGrid.on('scroll', function() {
                if ($(this).scrollTop() + $(this).innerHeight() >= $(this)[0].scrollHeight - 50) {
                    if (currentPage * iconsPerPage < filteredIcons.length) {
                        loadMoreIcons();
                    }
                }
            });

            $('.vep-icon-modal-close').on('click', closeModal);
            modal.on('click', function(e) {
                if ($(e.target).is('.vep-icon-modal-backdrop')) {
                    closeModal();
                }
            });

            $(document).on('click', '.vep-icon-item', function() {
                var icon = $(this).data('icon');
                $iconInput.val(icon).trigger('change');
                var parsed = parseIconPrefix(icon);
                $iconPreview.html('<i class="' + parsed.class + ' ' + parsed.name + '"></i>');
                closeModal();
            });

            $('#icon-search').on('input', function() {
                var searchTerm = $(this).val().toLowerCase();

                if (searchTerm === '') {
                    filteredIcons = allIcons.slice();
                } else {
                    filteredIcons = allIcons.filter(function(icon) {
                        return icon.toLowerCase().indexOf(searchTerm) > -1;
                    });
                }

                currentPage = 0;
                iconGrid.empty();
                loader.hide();
                loadMoreIcons();
            });

            $('#icon-search').focus();
        });

        $clearPickerBtn.on('click', function(e) {
            e.preventDefault();
            $iconInput.val('').trigger('input');
        });

        $iconInput.on('input', function() {
            var iconValue = $(this).val();
            if (iconValue) {
                var parsed = parseIconPrefix(iconValue);
                $iconPreview.html('<i class="' + parsed.class + ' ' + parsed.name + '"></i>');
            } else {
                var placeholderText = (typeof vepAdmin !== 'undefined' && vepAdmin.placeholderText) ? vepAdmin.placeholderText : 'Please select icon';
                $iconPreview.html('<span class="vep-icon-placeholder">' + placeholderText + '</span>');
            }
        });

        $form.find('input, textarea').on('change', function() {
            if ($form.serialize() !== initialData) {
                $(window).on('beforeunload', function() {
                    return 'You have unsaved changes.';
                });
            }
        });

        $form.on('submit', function() {
            $(window).off('beforeunload');
        });

        $(document).on('click', 'a', function() {
            $(window).off('beforeunload');
        });
    }
})(jQuery);
