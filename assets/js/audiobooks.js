/**
 * ADC Audiobooks JavaScript
 * Version: 1.4 - Player inline por capítulo (como rabanidjar.com)
 */

(function($) {
    'use strict';

    const STORAGE_KEY = 'adc_audiobook_progress';
    const SAVE_INTERVAL = 5000;
    const COMPLETION_THRESHOLD = 0.9;

    // Configuration from PHP
    const config = window.adc_audiobooks_config || {
        texts: {
            chapter: 'Capítulo',
            minute: 'Minuto',
            listened: 'Escuchado',
            continue_from: 'Continuar desde'
        }
    };

    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        const $detail = $('.adc-audiobook-detail');
        if ($detail.length) {
            initAudioTracking();
            loadAndDisplayProgress();
        }
    });

    /**
     * Get all progress from localStorage
     */
    function getAllProgress() {
        try {
            const data = localStorage.getItem(STORAGE_KEY);
            return data ? JSON.parse(data) : {};
        } catch (e) {
            console.warn('Error reading progress:', e);
            return {};
        }
    }

    /**
     * Get progress for a specific book
     */
    function getProgress(libroId) {
        return getAllProgress()[libroId] || null;
    }

    /**
     * Save progress
     */
    function saveProgress(libroId, chapter, time, duration) {
        try {
            const allProgress = getAllProgress();
            const existing = allProgress[libroId] || { completedChapters: [] };

            allProgress[libroId] = {
                currentChapter: chapter,
                currentTime: Math.floor(time),
                duration: Math.floor(duration || 0),
                completedChapters: existing.completedChapters || [],
                lastPlayed: new Date().toISOString()
            };

            localStorage.setItem(STORAGE_KEY, JSON.stringify(allProgress));
        } catch (e) {
            console.warn('Error saving progress:', e);
        }
    }

    /**
     * Mark chapter as complete
     */
    function markChapterComplete(libroId, chapter) {
        try {
            const allProgress = getAllProgress();
            const existing = allProgress[libroId] || { completedChapters: [] };

            if (!existing.completedChapters.includes(chapter)) {
                existing.completedChapters.push(chapter);
                existing.completedChapters.sort((a, b) => a - b);
            }

            allProgress[libroId] = existing;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(allProgress));
        } catch (e) {
            console.warn('Error marking chapter:', e);
        }
    }

    /**
     * Check if chapter is complete
     */
    function isChapterComplete(libroId, chapter) {
        const progress = getProgress(libroId);
        return progress?.completedChapters?.includes(chapter) || false;
    }

    /**
     * Format time
     */
    function formatTime(seconds) {
        if (!seconds || isNaN(seconds)) return '0:00';
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + (secs < 10 ? '0' : '') + secs;
    }

    /**
     * Load and display progress badges
     */
    function loadAndDisplayProgress() {
        $('.adc-chapter-item').each(function() {
            const $item = $(this);
            const chapter = parseInt($item.data('chapter'));
            const libroId = $item.data('libro');
            const progress = getProgress(libroId);
            const $badge = $item.find('.adc-chapter-badge');

            if (isChapterComplete(libroId, chapter)) {
                $badge.html('<span class="badge-listened">✓ ' + config.texts.listened + '</span>');
            } else if (progress && progress.currentChapter === chapter && progress.currentTime > 5) {
                $badge.html('<span class="badge-progress">▶ ' + config.texts.minute + ' ' + formatTime(progress.currentTime) + '</span>');

                // Restore saved time when audio loads
                const audio = $item.find('audio')[0];
                if (audio) {
                    audio.dataset.savedTime = progress.currentTime;
                }
            }
        });

        // Show continue listening banner
        showContinueBanner();
    }

    /**
     * Show continue listening banner
     */
    function showContinueBanner() {
        const $banner = $('#adc-continue-listening');
        const $firstItem = $('.adc-chapter-item').first();
        if (!$firstItem.length) return;

        const libroId = $firstItem.data('libro');
        const progress = getProgress(libroId);

        if (!progress || progress.currentTime < 10) return;

        const $chapterItem = $(`.adc-chapter-item[data-chapter="${progress.currentChapter}"]`);
        if (!$chapterItem.length) return;

        const chapterTitle = $chapterItem.find('.adc-chapter-title').text();
        const timeStr = formatTime(progress.currentTime);

        $banner.find('.adc-continue-chapter').text(
            config.texts.chapter + ' ' + String(progress.currentChapter).padStart(2, '0') + ' · ' + config.texts.minute + ' ' + timeStr
        );
        $banner.find('.adc-continue-title').text(chapterTitle);
        $banner.show();

        $('#adc-continue-btn').off('click').on('click', function() {
            scrollToChapter(progress.currentChapter);
            $banner.slideUp();
        });
    }

    /**
     * Scroll to chapter and play
     */
    function scrollToChapter(chapter) {
        const $item = $(`.adc-chapter-item[data-chapter="${chapter}"]`);
        if ($item.length) {
            $('html, body').animate({
                scrollTop: $item.offset().top - 100
            }, 500);

            setTimeout(function() {
                const audio = $item.find('audio')[0];
                if (audio) {
                    audio.play().catch(function() {});
                }
            }, 600);
        }
    }

    /**
     * Initialize audio tracking
     */
    function initAudioTracking() {
        $('.adc-chapter-audio').each(function() {
            const audio = this;
            const $item = $(audio).closest('.adc-chapter-item');
            const chapter = parseInt($item.data('chapter'));
            const libroId = $item.data('libro');
            let saveTimer = null;
            let hasRestored = false;

            // Restore position when metadata loads
            $(audio).on('loadedmetadata', function() {
                const savedTime = parseFloat(audio.dataset.savedTime) || 0;
                if (savedTime > 0 && !hasRestored) {
                    audio.currentTime = savedTime;
                    hasRestored = true;
                }
            });

            // Pause all others when playing
            $(audio).on('play', function() {
                $('.adc-chapter-audio').not(audio).each(function() {
                    this.pause();
                });

                // Highlight current chapter
                $('.adc-chapter-item').removeClass('playing');
                $item.addClass('playing');

                // Save progress periodically
                if (saveTimer) clearInterval(saveTimer);
                saveTimer = setInterval(function() {
                    if (!audio.paused && audio.duration) {
                        saveProgress(libroId, chapter, audio.currentTime, audio.duration);
                    }
                }, SAVE_INTERVAL);
            });

            // Save on pause
            $(audio).on('pause', function() {
                if (saveTimer) clearInterval(saveTimer);
                if (audio.duration) {
                    saveProgress(libroId, chapter, audio.currentTime, audio.duration);
                }
            });

            // Mark complete and play next when ended
            $(audio).on('ended', function() {
                if (saveTimer) clearInterval(saveTimer);
                markChapterComplete(libroId, chapter);
                updateBadge($item, 'complete');
                playNextChapter(libroId, chapter);
            });

            // Check 90% completion
            $(audio).on('timeupdate', function() {
                if (audio.duration && (audio.currentTime / audio.duration) >= COMPLETION_THRESHOLD) {
                    if (!isChapterComplete(libroId, chapter)) {
                        markChapterComplete(libroId, chapter);
                        updateBadge($item, 'complete');
                    }
                }
            });
        });
    }

    /**
     * Update badge
     */
    function updateBadge($item, type) {
        const $badge = $item.find('.adc-chapter-badge');
        if (type === 'complete') {
            $badge.html('<span class="badge-listened">✓ ' + config.texts.listened + '</span>');
        }
    }

    /**
     * Play next chapter
     */
    function playNextChapter(libroId, currentChapter) {
        const nextChapter = currentChapter + 1;
        const $nextItem = $(`.adc-chapter-item[data-chapter="${nextChapter}"][data-libro="${libroId}"]`);

        if ($nextItem.length) {
            $('html, body').animate({
                scrollTop: $nextItem.offset().top - 100
            }, 500);

            setTimeout(function() {
                const nextAudio = $nextItem.find('audio')[0];
                if (nextAudio) {
                    nextAudio.play().catch(function() {});
                }
            }, 800);
        }
    }

})(jQuery);
