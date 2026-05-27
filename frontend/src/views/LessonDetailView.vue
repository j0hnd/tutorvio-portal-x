<template>
  <div v-if="lesson" class="ld-page">

    <!-- Back -->
    <button class="ld-back" type="button" @click="router.back()">
      <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
        <path d="M9 2L4 7l5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      Back to Lessons
    </button>

    <!-- Two-column top layout -->
    <div class="ld-top-cols">

    <!-- Header card (left) -->
    <div class="ld-header-card">
      <div class="ld-header-card__top">
        <div class="ld-header-card__title-row">
          <h1 class="ld-lesson-title">{{ lesson.title }}</h1>
          <span :class="['ld-status', `ld-status--${statusClass}`]">{{ statusLabel }}</span>
          <span v-if="lesson.isTrial && lesson.status !== 'TRIAL'" class="ld-badge ld-badge--trial">Trial</span>
          <span v-if="lesson.isRecurring" class="ld-badge ld-badge--recur">↻ Recurring</span>
        </div>
      </div>

      <!-- Info grid -->
      <div class="ld-info-grid">
        <div class="ld-info-item">
          <span class="ld-info-label">Date</span>
          <span class="ld-info-value">{{ formattedDate }}</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Time</span>
          <span class="ld-info-value">{{ formattedStart }} – {{ formattedEnd }} ({{ durationMin }} min)</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Timezone</span>
          <span class="ld-info-value">{{ userTimezone }}</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">{{ role === 'STUDENT' ? 'Teacher' : 'Student' }}</span>
          <span class="ld-info-value">{{ role === 'STUDENT' ? lesson.teacherName : lesson.studentName }}</span>
        </div>
        <div class="ld-info-item">
          <span class="ld-info-label">Type</span>
          <span class="ld-info-value">{{ lesson.isTrial ? 'Trial' : lesson.isRecurring ? 'Recurring' : 'One-time' }}</span>
        </div>
        <div v-if="role === 'STUDENT'" class="ld-info-item">
          <span class="ld-info-label">Credits Remaining</span>
          <span class="ld-info-value" style="color: var(--tv-primary); font-weight: 600;">3 Credits</span>
        </div>
      </div>

      <!-- Before You Join tips (shown inside left card when join panel is active) -->
      <div v-if="['upcoming', 'soon', 'joinable', 'live'].includes(activeJoinState)" class="ld-join-col--tips">
        <p class="ld-tips__heading">Before you join</p>
        <ul class="ld-tips__list">
          <li>Use <strong>Google Chrome</strong> or <strong>Microsoft Edge</strong> for the best experience.</li>
          <li>Check your <strong>camera and microphone</strong> in your browser settings.</li>
          <li>Connect to a <strong>stable WiFi</strong> or wired network.</li>
          <li>Find a <strong>quiet place</strong> with good lighting.</li>
          <li>Have your <strong>notebook or materials</strong> ready.</li>
          <li>If you have issues, contact your teacher via the internal chat.</li>
        </ul>
      </div>
    </div>

    <!-- ─── Join Panel (right card) ─── -->
    <div :class="['ld-join-panel', `ld-join-panel--${activeJoinState}`]">

        <!-- ── State content ── -->
        <div class="ld-join-col ld-join-col--main">

          <!-- PENDING -->
          <template v-if="activeJoinState === 'pending'">
            <div class="ld-join-icon ld-join-icon--pending">
              <svg width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <circle cx="14" cy="14" r="11" stroke="currentColor" stroke-width="1.6"/>
                <path d="M14 8v6.5l4 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </div>
            <p class="ld-join-title">Awaiting Confirmation</p>
            <p class="ld-join-sub">This lesson is pending confirmation. You'll be notified once it's confirmed.</p>
          </template>

          <!-- UPCOMING (>30 min away) -->
          <template v-else-if="activeJoinState === 'upcoming'">
            <div class="ld-join-icon ld-join-icon--upcoming">
              <svg width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <circle cx="14" cy="14" r="11" stroke="currentColor" stroke-width="1.6"/>
                <path d="M14 8v6.5l4 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </div>
            <p class="ld-join-title">Class starts in</p>
            <div class="ld-countdown">
              <div v-if="countdown.days" class="ld-countdown-unit">
                <span class="ld-countdown-val">{{ countdown.days }}</span>
                <span class="ld-countdown-label">{{ countdown.days === 1 ? 'day' : 'days' }}</span>
              </div>
              <div v-if="countdown.days" class="ld-countdown-sep">:</div>
              <div class="ld-countdown-unit">
                <span class="ld-countdown-val">{{ String(countdown.hours).padStart(2, '0') }}</span>
                <span class="ld-countdown-label">hr</span>
              </div>
              <div class="ld-countdown-sep">:</div>
              <div class="ld-countdown-unit">
                <span class="ld-countdown-val">{{ String(countdown.minutes).padStart(2, '0') }}</span>
                <span class="ld-countdown-label">min</span>
              </div>
              <div class="ld-countdown-sep">:</div>
              <div class="ld-countdown-unit">
                <span class="ld-countdown-val">{{ String(countdown.seconds).padStart(2, '0') }}</span>
                <span class="ld-countdown-label">sec</span>
              </div>
            </div>
            <p class="ld-join-sub">The join button will appear 15 minutes before class starts.</p>
          </template>

          <!-- SOON (15–30 min away) -->
          <template v-else-if="activeJoinState === 'soon'">
            <div class="ld-join-icon ld-join-icon--soon">
              <svg width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <circle cx="14" cy="14" r="11" stroke="currentColor" stroke-width="1.6"/>
                <path d="M14 8v6.5l4 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </div>
            <p class="ld-join-title">Almost time!</p>
            <div class="ld-countdown ld-countdown--ticking">
              <div class="ld-countdown-unit">
                <span class="ld-countdown-val">{{ String(countdown.minutes).padStart(2, '0') }}</span>
                <span class="ld-countdown-label">min</span>
              </div>
              <div class="ld-countdown-sep">:</div>
              <div class="ld-countdown-unit">
                <span class="ld-countdown-val">{{ String(countdown.seconds).padStart(2, '0') }}</span>
                <span class="ld-countdown-label">sec</span>
              </div>
            </div>
            <p class="ld-join-sub">Get ready. The classroom will open shortly.</p>
          </template>

          <!-- JOINABLE (within 15 min before → end) -->
          <template v-else-if="activeJoinState === 'joinable'">
            <p class="ld-join-title">Your class is ready</p>
            <p class="ld-join-sub">
              with <strong>{{ role === 'STUDENT' ? lesson.teacherName : lesson.studentName }}</strong>
              · ends at {{ formattedEnd }}
            </p>
            <p class="ld-join-timer-inline">
              {{ String(countdown.hours).padStart(2, '0') }}:{{ String(countdown.minutes).padStart(2, '0') }}:{{ String(countdown.seconds).padStart(2, '0') }} remaining
            </p>
            <div class="ld-join-actions">
              <a
                v-if="lesson.meetingUrl"
                :href="lesson.meetingUrl"
                target="_blank"
                rel="noopener"
                class="ld-join-cta"
              >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                  <rect x="1" y="4" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M11 7.5l6-3v9l-6-3V7.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                Join Meeting
              </a>
              <!-- Future: <button class="ld-join-cta" @click="openEmbeddedMeet">Open Classroom</button> -->
            </div>
          </template>

          <!-- LIVE (status = IN_PROGRESS) -->
          <template v-else-if="activeJoinState === 'live'">
            <div class="ld-live-indicator">
              <div class="ld-live-dot" aria-hidden="true" />
              <span class="ld-live-label">Live now</span>
            </div>
            <p class="ld-join-title">Class is in progress</p>
            <p class="ld-join-sub">
              with <strong>{{ role === 'STUDENT' ? lesson.teacherName : lesson.studentName }}</strong>
            </p>
            <div class="ld-join-actions">
              <a
                v-if="lesson.meetingUrl"
                :href="lesson.meetingUrl"
                target="_blank"
                rel="noopener"
                class="ld-join-cta ld-join-cta--live"
              >
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                  <rect x="1" y="4" width="10" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/>
                  <path d="M11 7.5l6-3v9l-6-3V7.5z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                </svg>
                Rejoin Meeting
              </a>
            </div>
          </template>

          <!-- EXPIRED -->
          <template v-else-if="activeJoinState === 'expired'">
            <div class="ld-join-icon ld-join-icon--expired">
              <svg width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <circle cx="14" cy="14" r="11" stroke="currentColor" stroke-width="1.6"/>
                <path d="M9 9l10 10M19 9L9 19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
              </svg>
            </div>
            <p class="ld-join-title">Class time has passed</p>
            <p class="ld-join-sub">This lesson's scheduled time has ended. Please contact your teacher or admin.</p>
          </template>

          <!-- COMPLETED -->
          <template v-else-if="activeJoinState === 'completed'">
            <div class="ld-join-icon ld-join-icon--completed">
              <svg width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <circle cx="14" cy="14" r="11" stroke="currentColor" stroke-width="1.6"/>
                <path d="M9 14.5l3.5 3.5 6.5-7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <p class="ld-join-title">Class completed</p>
            <p class="ld-join-sub">Review your lesson notes, materials, and homework below.</p>
            <button class="ld-jump-notes-btn" type="button" @click="activeTab = 'notes'">
              View Notes →
            </button>
          </template>

          <!-- TERMINAL (cancelled / missed / rescheduled) -->
          <template v-else-if="activeJoinState === 'terminal'">
            <div class="ld-join-icon ld-join-icon--expired">
              <svg width="32" height="32" viewBox="0 0 28 28" fill="none" aria-hidden="true">
                <circle cx="14" cy="14" r="11" stroke="currentColor" stroke-width="1.6"/>
                <path d="M14 8v5.5M14 16.5v1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
              </svg>
            </div>
            <p class="ld-join-title">
              <span v-if="lesson.status === 'CANCELLED'">Lesson Cancelled</span>
              <span v-else-if="lesson.status === 'RESCHEDULED'">Lesson Rescheduled</span>
              <span v-else-if="lesson.status === 'MISSED_BY_STUDENT'">Missed by Student</span>
              <span v-else-if="lesson.status === 'MISSED_BY_TEACHER'">Missed by Teacher</span>
            </p>
            <p class="ld-join-sub">
              <span v-if="lesson.status === 'CANCELLED'">This lesson has been cancelled.</span>
              <span v-else-if="lesson.status === 'RESCHEDULED'">This lesson has been rescheduled. Check the updated time on the left.</span>
              <span v-else-if="lesson.status === 'MISSED_BY_STUDENT'">Marked as missed — student did not attend.</span>
              <span v-else-if="lesson.status === 'MISSED_BY_TEACHER'">Marked as missed — teacher did not attend.</span>
            </p>
          </template>

        </div>

      <!--
        ── Embedded Meet Placeholder ─────────────────────────────────────────────
        Future: replace external meeting link with embedded classroom.

        <div v-if="activeJoinState === 'joinable' || activeJoinState === 'live'" class="ld-embedded-meet">
          <iframe
            :src="`https://meet.tutorvio.com/${lesson.meetingRoomId}?token=${meetToken}`"
            allow="camera; microphone; fullscreen; display-capture"
            class="ld-embedded-meet__frame"
          />
        </div>
        ──────────────────────────────────────────────────────────────────────── -->

      <!-- ── Dev state tester ── -->
      <div class="ld-dev-bar">
        <span class="ld-dev-bar__label">
          <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
            <path d="M2 4l2-2 2 2M8 4l2-2 2 2M1 8h10M3 10h6" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Test state:
        </span>
        <div class="ld-dev-bar__pills">
          <button
            v-for="s in DEV_STATES"
            :key="s.value"
            :class="['ld-dev-pill', { 'ld-dev-pill--active': devStateOverride === s.value }]"
            type="button"
            @click="devStateOverride = devStateOverride === s.value ? null : s.value"
          >{{ s.label }}</button>
        </div>
      </div>

    </div>

    </div><!-- /ld-top-cols -->

    <!-- Tabs -->
    <div class="ld-tabs" role="tablist" aria-label="Lesson sections">
      <button
        v-for="tab in visibleTabs"
        :key="tab.key"
        :class="['ld-tab', { 'ld-tab--active': activeTab === tab.key }]"
        role="tab"
        :aria-selected="activeTab === tab.key"
        type="button"
        @click="activeTab = tab.key"
      >
        {{ tab.label }}
        <span v-if="tab.count" class="ld-tab__count">{{ tab.count }}</span>
        <span v-if="tab.pending" class="ld-tab__pending-dot" aria-label="Pending" />
      </button>
    </div>

    <!-- Tab content -->
    <div class="ld-tab-body">

      <!-- ── Notes ── -->
      <section v-if="activeTab === 'notes'" class="ld-section">

        <!-- READ VIEW -->
        <template v-if="lessonNote && !showNoteModal">
          <div class="ld-section__header">
            <h2 class="ld-section__title">Lesson Notes</h2>
            <button v-if="canManageNotes" class="ld-add-btn" type="button" @click="openNoteForm">Edit Notes</button>
          </div>

          <div class="ld-note-read">
            <div class="ld-note-status-row">
              <span :class="['ld-note-status', lessonNote.status === 'submitted' ? 'ld-note-status--submitted' : 'ld-note-status--draft']">
                {{ lessonNote.status === 'submitted' ? 'Submitted' : 'Draft' }}
              </span>
              <span class="ld-note-date">
                {{ lessonNote.status === 'submitted' ? 'Submitted' : 'Saved' }} {{ formatDateTime(lessonNote.updatedAt ?? lessonNote.createdAt) }}
              </span>
            </div>

            <div class="ld-note-fields">
              <div v-if="lessonNote.lessonObjective" class="ld-note-field">
                <span class="ld-note-field__label">Lesson Objective</span>
                <p class="ld-note-field__value">{{ lessonNote.lessonObjective }}</p>
              </div>
              <div v-if="lessonNote.topicsCovered" class="ld-note-field">
                <span class="ld-note-field__label">Topics Covered</span>
                <p class="ld-note-field__value">{{ lessonNote.topicsCovered }}</p>
              </div>
              <div v-if="lessonNote.vocabularyLearned" class="ld-note-field">
                <span class="ld-note-field__label">Vocabulary Learned</span>
                <p class="ld-note-field__value">{{ lessonNote.vocabularyLearned }}</p>
              </div>
              <div v-if="lessonNote.grammarFocus" class="ld-note-field">
                <span class="ld-note-field__label">Grammar Focus</span>
                <p class="ld-note-field__value">{{ lessonNote.grammarFocus }}</p>
              </div>
              <div v-if="lessonNote.pronunciationIssues" class="ld-note-field">
                <span class="ld-note-field__label">Pronunciation Issues</span>
                <p class="ld-note-field__value">{{ lessonNote.pronunciationIssues }}</p>
              </div>
              <div v-if="lessonNote.speakingConfidence" class="ld-note-field">
                <span class="ld-note-field__label">Speaking Confidence</span>
                <p class="ld-note-field__value" style="text-transform: capitalize;">{{ lessonNote.speakingConfidence }}</p>
              </div>
              <div v-if="lessonNote.homeworkAssignment" class="ld-note-field ld-note-field--full">
                <span class="ld-note-field__label">Homework Assignment</span>
                <p class="ld-note-field__value">{{ lessonNote.homeworkAssignment }}</p>
              </div>
              <div v-if="lessonNote.nextLessonRecommendation" class="ld-note-field ld-note-field--full">
                <span class="ld-note-field__label">Next Lesson Recommendation</span>
                <p class="ld-note-field__value">{{ lessonNote.nextLessonRecommendation }}</p>
              </div>
            </div>

            <div v-if="canManageNotes && lessonNote.internalNote" class="ld-note-internal">
              <div class="ld-note-internal__heading">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
                  <path d="M6 1a5 5 0 1 0 0 10A5 5 0 0 0 6 1zm0 4v3M6 4.5v-.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                </svg>
                Internal Only
              </div>
              <p class="ld-note-internal__body">{{ lessonNote.internalNote }}</p>
            </div>

            <div v-if="lesson && ['COMPLETED', 'MISSED_BY_STUDENT', 'MISSED_BY_TEACHER'].includes(lesson.status) && lessonNote.status === 'draft'" class="ld-notice ld-notice--missed" style="margin-top: 0;">
              <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1.5l5.5 10H1.5L7 1.5z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/><path d="M7 6v2.5M7 10.5v.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg>
              This lesson is completed but the note is still a draft. Submit it when ready.
            </div>
          </div>
        </template>

        <!-- EMPTY VIEW -->
        <template v-else>
          <div class="ld-section__header">
            <h2 class="ld-section__title">Lesson Notes</h2>
            <button v-if="canManageNotes" class="ld-add-btn" type="button" @click="openNoteForm">Write Notes</button>
          </div>
          <p class="ld-empty">No lesson notes yet.</p>
        </template>

      </section>

      <!-- ── Attendance ── -->
      <section v-if="activeTab === 'attendance'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Attendance</h2>
          <button v-if="canMarkAttendance" class="ld-add-btn" type="button" @click="openAttendanceForm">
            {{ attendance ? 'Edit' : 'Mark Attendance' }}
          </button>
        </div>

        <!-- Current attendance -->
        <div v-if="attendance" class="ld-attendance-card">
          <div v-if="attendance.isOverridden" class="ld-attendance-override-notice">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M7 1.5L12.5 11H1.5L7 1.5z" stroke="currentColor" stroke-width="1.3" stroke-linejoin="round"/><path d="M7 5.5v3M7 10h.01" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            This record was overridden by an admin.
          </div>
          <div class="ld-attendance-row">
            <span class="ld-attendance-who">Teacher</span>
            <span :class="['ld-attendance-status', `ld-attendance-status--${attStatusClass(attendance.teacherStatus)}`]">
              {{ attStatusLabel(attendance.teacherStatus) }}
            </span>
          </div>
          <div class="ld-attendance-row">
            <span class="ld-attendance-who">Student</span>
            <span :class="['ld-attendance-status', `ld-attendance-status--${attStatusClass(attendance.studentStatus)}`]">
              {{ attStatusLabel(attendance.studentStatus) }}
            </span>
          </div>
          <div v-if="attendance.absenceReason" class="ld-attendance-reason">
            <span class="ld-attendance-reason__label">Reason</span>
            <span class="ld-attendance-reason__text">{{ attendance.absenceReason }}</span>
          </div>
          <div v-if="attendance.comments" class="ld-attendance-reason">
            <span class="ld-attendance-reason__label">Comments</span>
            <span class="ld-attendance-reason__text">{{ attendance.comments }}</span>
          </div>
          <p class="ld-attendance-meta">Marked {{ formatDateTime(attendance.markedAt) }}</p>
        </div>
        <p v-else class="ld-empty">Attendance has not been marked yet.</p>
      </section>

      <!-- ── Homework ── -->
      <section v-if="activeTab === 'homework'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Homework</h2>
          <button v-if="canAssignHomework" class="ld-add-btn" type="button" @click="showHomeworkModal = true">+ Assign</button>
        </div>

        <!-- Homework list -->
        <div v-if="homework.length" class="ld-hw-list">
          <div v-for="hw in homework" :key="hw.id" class="ld-hw-item">
            <div class="ld-hw-item__header">
              <span class="ld-hw-item__title">{{ hw.title }}</span>
              <span :class="['ld-hw-status', `ld-hw-status--${hw.status.toLowerCase()}`]">{{ hw.status }}</span>
            </div>
            <p class="ld-hw-item__desc">{{ hw.description }}</p>
            <div class="ld-hw-item__meta">
              <span>Due: <strong>{{ hw.dueDate }}</strong></span>
              <span>Student: {{ hw.studentName }}</span>
            </div>
            <div v-if="hw.feedback" class="ld-hw-feedback">
              <span class="ld-hw-feedback__label">Feedback</span>
              <p class="ld-hw-feedback__text">{{ hw.feedback }}</p>
              <span v-if="hw.grade" class="ld-hw-grade">{{ hw.grade }}</span>
            </div>
            <div v-if="hw.submissionUrl" class="ld-hw-submission">
              <a :href="hw.submissionUrl" target="_blank" rel="noopener" class="ld-link">View Submission →</a>
            </div>
          </div>
        </div>
        <p v-else class="ld-empty">No homework assigned for this lesson.</p>
      </section>

      <!-- ── Materials ── -->
      <section v-if="activeTab === 'materials'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Materials</h2>
          <button v-if="canUploadMaterials" class="ld-add-btn" type="button" @click="showMaterialModal = true">+ Add</button>
        </div>

        <!-- Materials list -->
        <div v-if="materials.length" class="ld-mat-list">
          <div v-for="mat in materials" :key="mat.id" class="ld-mat-item">
            <span :class="['ld-mat-type-icon', `ld-mat-type-icon--${mat.type.toLowerCase()}`]">
              {{ typeIcon(mat.type) }}
            </span>
            <div class="ld-mat-item__body">
              <a :href="mat.url" target="_blank" rel="noopener" class="ld-mat-item__title">{{ mat.title }}</a>
              <span class="ld-mat-item__meta">{{ mat.type }} · Uploaded by {{ mat.uploadedByName }} · {{ formatDate(mat.uploadedAt) }}</span>
            </div>
            <button v-if="canUploadMaterials" class="ld-icon-btn ld-icon-btn--danger" type="button" title="Remove" @click="removeMaterial(mat.id)">
              <svg width="13" height="13" viewBox="0 0 13 13" fill="none"><path d="M2.5 2.5l8 8M10.5 2.5l-8 8" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
            </button>
          </div>
        </div>
        <p v-else class="ld-empty">No materials for this lesson yet.</p>
      </section>

      <!-- ── Student Profile Summary ── -->
      <section v-if="activeTab === 'profile'" class="ld-section">
        <div class="ld-section__header">
          <h2 class="ld-section__title">Student Profile</h2>
        </div>
        <div v-if="studentDetails" class="ld-student-profile-card">
          <div class="ld-profile-info-grid">
            <div class="ld-profile-info-item">
              <span class="ld-profile-info-label">English Level</span>
              <span class="ld-profile-info-value">{{ studentDetails.studentProfile?.englishLevel ? levelLabel(studentDetails.studentProfile.englishLevel) : '—' }}</span>
            </div>
            <div class="ld-profile-info-item">
              <span class="ld-profile-info-label">Program</span>
              <span class="ld-profile-info-value">{{ studentDetails.studentProfile?.program || '—' }}</span>
            </div>
            <div class="ld-profile-info-item">
              <span class="ld-profile-info-label">Class Type</span>
              <span class="ld-profile-info-value">{{ studentDetails.studentProfile?.classType || '—' }}</span>
            </div>
            <div class="ld-profile-info-item">
              <span class="ld-profile-info-label">Start Date</span>
              <span class="ld-profile-info-value">{{ studentDetails.studentProfile?.startDate || '—' }}</span>
            </div>
            <div v-if="studentDetails.studentProfile?.goals" class="ld-profile-info-item ld-profile-info-item--full">
              <span class="ld-profile-info-label">Goals</span>
              <p class="ld-profile-info-text">{{ studentDetails.studentProfile.goals }}</p>
            </div>
            <div v-if="studentDetails.studentProfile?.learningConcerns" class="ld-profile-info-item ld-profile-info-item--full">
              <span class="ld-profile-info-label">Learning Concerns</span>
              <p class="ld-profile-info-text">{{ studentDetails.studentProfile.learningConcerns }}</p>
            </div>
            <div v-if="studentDetails.studentProfile?.notes" class="ld-profile-info-item ld-profile-info-item--full">
              <span class="ld-profile-info-label">Academic Notes</span>
              <p class="ld-profile-info-text">{{ studentDetails.studentProfile.notes }}</p>
            </div>
          </div>
        </div>
        <p v-else class="ld-empty">No student profile details available.</p>
      </section>

    </div>

    <!-- ── Notes Modal ── -->
    <TVModal v-model="showNoteModal" title="Write Lesson Notes" maxWidth="700px">
      <div class="ld-note-form-section">
        <p class="ld-note-form-section__heading">Lesson Summary</p>
        <TVInput v-model="nObjective" label="Lesson Objective" placeholder="What was the goal of this lesson?" />
        <TVInput v-model="nTopics" label="Topics Covered" placeholder="Main topics discussed" />
        <div class="ld-form-row">
          <TVInput v-model="nVocab" label="Vocabulary Learned" placeholder="Key words introduced" />
          <TVInput v-model="nGrammar" label="Grammar Focus" placeholder="Grammar points practiced" />
        </div>
        <div class="ld-form-row">
          <TVInput v-model="nPronunciation" label="Pronunciation Issues" placeholder="Areas to work on" />
          <TVSelect v-model="nConfidence" label="Speaking Confidence" :options="[
            { value: '', label: 'Not assessed' },
            { value: 'low', label: 'Low' },
            { value: 'medium', label: 'Medium' },
            { value: 'high', label: 'High' },
          ]" />
        </div>
      </div>
      <div class="ld-note-form-section">
        <p class="ld-note-form-section__heading">Follow-up</p>
        <div class="ld-field">
          <label class="ld-label">Homework Assignment</label>
          <textarea v-model="nHomework" class="ld-textarea" rows="2" placeholder="What should the student practice?" />
        </div>
        <div class="ld-field">
          <label class="ld-label">Next Lesson Recommendation</label>
          <textarea v-model="nNextLesson" class="ld-textarea" rows="2" placeholder="Suggested focus for next session" />
        </div>
      </div>
      <div v-if="canManageNotes" class="ld-note-internal-form">
        <p class="ld-note-internal-form__heading">Internal Note (Teacher/Admin only)</p>
        <textarea v-model="nInternal" class="ld-textarea" rows="2" placeholder="Private notes not visible to student" />
      </div>
      <template #footer>
        <button class="ld-btn ld-btn--ghost" type="button" @click="showNoteModal = false">Cancel</button>
        <button class="ld-btn ld-btn--ghost" type="button" @click="saveNoteForm('draft')">Save Draft</button>
        <button class="ld-btn ld-btn--primary" type="button" @click="saveNoteForm('submitted')">Submit Notes</button>
      </template>
    </TVModal>

    <!-- ── Attendance Modal ── -->
    <TVModal v-model="showAttendanceModal" title="Mark Attendance" maxWidth="480px">
      <TVSelect v-model="attTeacher" label="Teacher Status" :options="attendanceOptions" />
      <TVSelect v-model="attStudent" label="Student Status" :options="attendanceOptions" />
      <div class="ld-field">
        <label class="ld-label">Absence Reason</label>
        <input v-model="attReason" class="ld-input" type="text" placeholder="If absent, reason for absence" />
      </div>
      <div class="ld-field">
        <label class="ld-label">Comments</label>
        <textarea v-model="attComments" class="ld-textarea" rows="2" placeholder="Additional notes" />
      </div>
      <template #footer>
        <button class="ld-btn ld-btn--ghost" type="button" @click="showAttendanceModal = false">Cancel</button>
        <button class="ld-btn ld-btn--primary" type="button" @click="saveAttendance">Save</button>
      </template>
    </TVModal>

    <!-- ── Homework Modal ── -->
    <TVModal v-model="showHomeworkModal" title="Assign Homework" maxWidth="480px">
      <TVInput v-model="hwTitle" label="Title" placeholder="e.g. Practice modal verbs" required />
      <div class="ld-field">
        <label class="ld-label">Description</label>
        <textarea v-model="hwDescription" class="ld-textarea" rows="3" placeholder="Instructions for the student" />
      </div>
      <TVDatePicker v-model="hwDueDate" label="Due Date" :min="today" required />
      <template #footer>
        <button class="ld-btn ld-btn--ghost" type="button" @click="showHomeworkModal = false">Cancel</button>
        <button class="ld-btn ld-btn--primary" type="button" :disabled="!hwTitle.trim() || !hwDueDate" @click="saveHomework">Assign</button>
      </template>
    </TVModal>

    <!-- ── Materials Modal ── -->
    <TVModal v-model="showMaterialModal" title="Add Material" maxWidth="480px">
      <TVInput v-model="matTitle" label="Title" placeholder="e.g. Lesson slides" required />
      <TVSelect v-model="matType" label="Type" :options="materialTypeOptions" />
      <TVInput v-model="matUrl" label="URL" placeholder="https://..." required />
      <template #footer>
        <button class="ld-btn ld-btn--ghost" type="button" @click="showMaterialModal = false">Cancel</button>
        <button class="ld-btn ld-btn--primary" type="button" :disabled="!matTitle.trim() || !matUrl.trim()" @click="saveMaterial">Add Material</button>
      </template>
    </TVModal>

  </div>

  <!-- Not found -->
  <div v-else class="ld-not-found">
    <p>Lesson not found.</p>
    <button class="ld-btn ld-btn--ghost" type="button" @click="router.push({ name: 'Lessons' })">Back to Lessons</button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useScheduleStore } from '@/stores/schedule'
import { useAuthStore } from '@/stores/auth'
import { useUsersStore } from '@/stores/users'
import { useViewAs } from '@/composables/useViewAs'
import { useToast } from '@/composables/useToast'
import TVSelect from '@/components/ui/TVSelect.vue'
import TVModal from '@/components/ui/TVModal.vue'
import TVInput from '@/components/ui/TVInput.vue'
import TVDatePicker from '@/components/ui/TVDatePicker.vue'
import type { AttendanceStatus, MaterialType, NoteStatus, SpeakingConfidence } from '@/stores/schedule'

const route    = useRoute()
const router   = useRouter()
const schedule = useScheduleStore()
const auth     = useAuthStore()
const usersStore = useUsersStore()
const toast      = useToast()
const { effectiveRole } = useViewAs()

const lessonId = route.params.id as string
const lesson   = computed(() => schedule.getLesson(lessonId))

onMounted(async () => {
  if (!usersStore.users.length) {
    await usersStore.fetchUsers()
  }
})

const studentDetails = computed(() => {
  if (!lesson.value) return null
  return usersStore.getUserById(lesson.value.studentId)
})

function levelLabel(level: string): string {
  const map: Record<string, string> = {
    BEGINNER: 'Beginner',
    ELEMENTARY: 'Elementary',
    INTERMEDIATE: 'Intermediate',
    UPPER_INTERMEDIATE: 'Upper Intermediate',
    ADVANCED: 'Advanced',
    PROFICIENCY: 'Proficiency',
  }
  return map[level] ?? level
}

// ── Role guards ──
const role   = computed(() => effectiveRole.value)
const userId = computed(() => auth.user?.id ?? '')

const isAssignedTeacher  = computed(() => role.value === 'TEACHER' && lesson.value?.teacherId === userId.value)
const canManageNotes     = computed(() => role.value === 'ADMIN' || isAssignedTeacher.value)
const canMarkAttendance  = computed(() => role.value === 'ADMIN' || isAssignedTeacher.value)
const canAssignHomework  = computed(() => role.value === 'ADMIN' || isAssignedTeacher.value)
const canUploadMaterials = computed(() => role.value === 'ADMIN' || isAssignedTeacher.value)

// ── Status helpers ──
const statusClass = computed(() => {
  switch (lesson.value?.status) {
    case 'SCHEDULED':            return 'scheduled'
    case 'IN_PROGRESS':          return 'live'
    case 'COMPLETED':            return 'completed'
    case 'CANCELLED':            return 'cancelled'
    case 'MISSED_BY_STUDENT':
    case 'MISSED_BY_TEACHER':    return 'missed'
    case 'TRIAL':                return 'trial'
    case 'RESCHEDULED':          return 'rescheduled'
    case 'PENDING_CONFIRMATION': return 'pending'
    default:                     return 'scheduled'
  }
})

const statusLabel = computed(() => {
  switch (lesson.value?.status) {
    case 'SCHEDULED':            return 'Scheduled'
    case 'IN_PROGRESS':          return 'Live'
    case 'COMPLETED':            return 'Completed'
    case 'CANCELLED':            return 'Cancelled'
    case 'MISSED_BY_STUDENT':    return 'Missed by Student'
    case 'MISSED_BY_TEACHER':    return 'Missed by Teacher'
    case 'TRIAL':                return 'Trial'
    case 'RESCHEDULED':          return 'Rescheduled'
    case 'PENDING_CONFIRMATION': return 'Pending Confirmation'
    default:                     return lesson.value?.status ?? ''
  }
})


// ── Countdown timer ──
interface Countdown { days: number; hours: number; minutes: number; seconds: number }

const now      = ref(new Date())
let timerHandle: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  timerHandle = setInterval(() => { now.value = new Date() }, 1_000)
})
onUnmounted(() => {
  if (timerHandle !== null) clearInterval(timerHandle)
})

function msToCountdown(ms: number): Countdown {
  if (ms <= 0) return { days: 0, hours: 0, minutes: 0, seconds: 0 }
  const totalSec = Math.floor(ms / 1000)
  return {
    days:    Math.floor(totalSec / 86400),
    hours:   Math.floor((totalSec % 86400) / 3600),
    minutes: Math.floor((totalSec % 3600) / 60),
    seconds: totalSec % 60,
  }
}

// ms until class starts (negative when started)
const msUntilStart = computed(() => {
  if (!lesson.value) return 0
  return new Date(lesson.value.startTime).getTime() - now.value.getTime()
})

// ms until class ends (negative when ended)
const msUntilEnd = computed(() => {
  if (!lesson.value) return 0
  return new Date(lesson.value.endTime).getTime() - now.value.getTime()
})

const countdown = computed<Countdown>(() => {
  if (msUntilStart.value > 0) return msToCountdown(msUntilStart.value)
  if (msUntilEnd.value > 0)   return msToCountdown(msUntilEnd.value)
  return { days: 0, hours: 0, minutes: 0, seconds: 0 }
})

// ── Join state machine ──
type JoinState = 'terminal' | 'pending' | 'upcoming' | 'soon' | 'joinable' | 'live' | 'expired' | 'completed'

const JOIN_OPEN_MS  = 15 * 60 * 1000
const SOON_START_MS = 30 * 60 * 1000

const joinState = computed<JoinState>(() => {
  const s = lesson.value?.status
  if (!s) return 'terminal'
  if (['CANCELLED', 'MISSED_BY_STUDENT', 'MISSED_BY_TEACHER', 'RESCHEDULED'].includes(s)) return 'terminal'
  if (s === 'COMPLETED') return 'completed'
  if (s === 'PENDING_CONFIRMATION') return 'pending'
  if (s === 'IN_PROGRESS') return 'live'
  if (msUntilEnd.value <= 0)                       return 'expired'
  if (msUntilStart.value <= 0)                     return 'joinable'
  if (msUntilStart.value <= JOIN_OPEN_MS)          return 'joinable'
  if (msUntilStart.value <= SOON_START_MS)         return 'soon'
  return 'upcoming'
})

// ── Dev state tester ──
const DEV_STATES: { value: JoinState; label: string }[] = [
  { value: 'upcoming',  label: 'Upcoming' },
  { value: 'soon',      label: 'Soon' },
  { value: 'joinable',  label: 'Joinable' },
  { value: 'live',      label: 'Live' },
  { value: 'expired',   label: 'Expired' },
  { value: 'completed', label: 'Completed' },
  { value: 'pending',   label: 'Pending' },
  { value: 'terminal',  label: 'Terminal' },
]
const devStateOverride = ref<JoinState | null>(null)
const activeJoinState  = computed<JoinState>(() => devStateOverride.value ?? joinState.value)

// ── Formatted fields ──
const userTimezone = computed(() => auth.user?.timezone ?? 'Asia/Manila')

const formattedDate  = computed(() => lesson.value
  ? new Date(lesson.value.startTime).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }) : '')
const formattedStart = computed(() => lesson.value
  ? new Date(lesson.value.startTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '')
const formattedEnd   = computed(() => lesson.value
  ? new Date(lesson.value.endTime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : '')
const durationMin    = computed(() => lesson.value
  ? Math.round((new Date(lesson.value.endTime).getTime() - new Date(lesson.value.startTime).getTime()) / 60_000) : 0)

// ── Data ──
const attendance = computed(() => schedule.getAttendanceForLesson(lessonId))
const materials  = computed(() => schedule.getMaterialsForLesson(lessonId))
const homework   = computed(() => schedule.getHomeworkForLesson(lessonId))
const lessonNote = computed(() => schedule.getNoteForLesson(lessonId))

// ── Tabs ──
type TabKey = 'notes' | 'attendance' | 'homework' | 'materials' | 'profile'
const activeTab = ref<TabKey>('notes')

const visibleTabs = computed(() => {
  const hasSubmittedNote = lessonNote.value?.status === 'submitted'
  const needsNote = lesson.value && ['COMPLETED', 'MISSED_BY_STUDENT', 'MISSED_BY_TEACHER'].includes(lesson.value.status) && !hasSubmittedNote && canManageNotes.value
  const tabs: { key: TabKey; label: string; count?: number; pending?: boolean }[] = [
    { key: 'notes',      label: 'Notes',      count: hasSubmittedNote ? 1 : undefined, pending: !!needsNote },
    { key: 'attendance', label: 'Attendance' },
    { key: 'homework',   label: 'Homework',   count: homework.value.length || undefined },
    { key: 'materials',  label: 'Materials',  count: materials.value.length || undefined },
  ]
  if (['TEACHER', 'ADMIN', 'STAFF'].includes(role.value)) {
    tabs.push({ key: 'profile', label: 'Student Profile' })
  }
  return tabs
})

// ── Notes modal ──
const showNoteModal  = ref(false)
const nObjective     = ref('')
const nTopics        = ref('')
const nVocab         = ref('')
const nGrammar       = ref('')
const nPronunciation = ref('')
const nConfidence    = ref<SpeakingConfidence>('')
const nHomework      = ref('')
const nNextLesson    = ref('')
const nInternal      = ref('')

function openNoteForm(): void {
  const n = lessonNote.value
  nObjective.value     = n?.lessonObjective ?? ''
  nTopics.value        = n?.topicsCovered ?? ''
  nVocab.value         = n?.vocabularyLearned ?? ''
  nGrammar.value       = n?.grammarFocus ?? ''
  nPronunciation.value = n?.pronunciationIssues ?? ''
  nConfidence.value    = n?.speakingConfidence ?? ''
  nHomework.value      = n?.homeworkAssignment ?? ''
  nNextLesson.value    = n?.nextLessonRecommendation ?? ''
  nInternal.value      = n?.internalNote ?? ''
  showNoteModal.value  = true
}

function saveNoteForm(submitStatus: NoteStatus): void {
  schedule.saveNote(lessonId, userId.value, auth.user ? `${auth.user.firstName} ${auth.user.lastName}` : 'Unknown', {
    lessonObjective: nObjective.value,
    topicsCovered: nTopics.value,
    vocabularyLearned: nVocab.value,
    grammarFocus: nGrammar.value,
    pronunciationIssues: nPronunciation.value,
    speakingConfidence: nConfidence.value,
    homeworkAssignment: nHomework.value,
    nextLessonRecommendation: nNextLesson.value,
    internalNote: nInternal.value,
    status: submitStatus,
  })
  showNoteModal.value = false
  toast.success(submitStatus === 'submitted' ? 'Note submitted!' : 'Draft saved.')
}

// ── Attendance modal ──
const showAttendanceModal = ref(false)
const attTeacher  = ref<AttendanceStatus>('PRESENT')
const attStudent  = ref<AttendanceStatus>('PRESENT')
const attReason   = ref('')
const attComments = ref('')

const attendanceOptions = [
  { value: 'PRESENT',               label: 'Present' },
  { value: 'LATE',                  label: 'Late' },
  { value: 'ABSENT_WITH_NOTICE',    label: 'Absent with notice' },
  { value: 'ABSENT_WITHOUT_NOTICE', label: 'Absent without notice' },
  { value: 'EXCUSED',               label: 'Excused' },
  { value: 'TEACHER_ABSENT',        label: 'Teacher absent' },
  { value: 'RESCHEDULED',           label: 'Rescheduled' },
]

function attStatusLabel(status: AttendanceStatus): string {
  return attendanceOptions.find(o => o.value === status)?.label ?? status
}

function attStatusClass(status: AttendanceStatus): string {
  if (status === 'PRESENT') return 'present'
  if (status === 'LATE') return 'late'
  if (status === 'ABSENT_WITH_NOTICE') return 'absent-notice'
  if (status === 'ABSENT_WITHOUT_NOTICE') return 'absent'
  if (status === 'EXCUSED') return 'excused'
  if (status === 'TEACHER_ABSENT') return 'absent'
  if (status === 'RESCHEDULED') return 'rescheduled'
  return 'default'
}

function openAttendanceForm(): void {
  const a = attendance.value
  attTeacher.value        = a?.teacherStatus ?? 'PRESENT'
  attStudent.value        = a?.studentStatus ?? 'PRESENT'
  attReason.value         = a?.absenceReason ?? ''
  attComments.value       = a?.comments ?? ''
  showAttendanceModal.value = true
}

function saveAttendance(): void {
  schedule.markAttendance(lessonId, attTeacher.value, attStudent.value, attReason.value, userId.value, attComments.value)
  showAttendanceModal.value = false
  toast.success('Attendance saved successfully!')
}

// ── Homework modal ──
const showHomeworkModal = ref(false)
const hwTitle       = ref('')
const hwDescription = ref('')
const hwDueDate     = ref('')
const today         = new Date().toLocaleDateString('sv-SE')

function saveHomework(): void {
  if (!hwTitle.value.trim() || !hwDueDate.value) return
  const studentId   = lesson.value?.studentId ?? ''
  const studentName = lesson.value?.studentName ?? ''
  schedule.addHomework(lessonId, hwTitle.value.trim(), hwDescription.value.trim(), hwDueDate.value, studentId, studentName)
  hwTitle.value = ''; hwDescription.value = ''; hwDueDate.value = ''
  showHomeworkModal.value = false
}

// ── Materials modal ──
const showMaterialModal = ref(false)
const matTitle = ref('')
const matType  = ref<MaterialType>('PDF')
const matUrl   = ref('')

const materialTypeOptions = [
  { value: 'PDF',      label: 'PDF' },
  { value: 'VIDEO',    label: 'Video' },
  { value: 'LINK',     label: 'Link' },
  { value: 'DOCUMENT', label: 'Document' },
  { value: 'IMAGE',    label: 'Image' },
]

function saveMaterial(): void {
  if (!matTitle.value.trim() || !matUrl.value.trim()) return
  const uploaderName = auth.user ? `${auth.user.firstName} ${auth.user.lastName}` : 'Unknown'
  schedule.addMaterial(lessonId, matTitle.value.trim(), matType.value, matUrl.value.trim(), uploaderName)
  matTitle.value = ''; matUrl.value = ''; matType.value = 'PDF'
  showMaterialModal.value = false
}

function removeMaterial(id: string): void {
  schedule.deleteMaterial(id)
}

function typeIcon(type: MaterialType): string {
  switch (type) {
    case 'PDF':      return '📄'
    case 'VIDEO':    return '🎬'
    case 'LINK':     return '🔗'
    case 'DOCUMENT': return '📝'
    case 'IMAGE':    return '🖼'
    default:         return '📎'
  }
}

// ── Helpers ──
function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true })
}
</script>

<style scoped>
.ld-page {
  padding: var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-5);
}

@media (max-width: 767px) {
  .ld-page { padding: var(--tv-space-4); }
}

/* Back */
.ld-back {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  font-size: var(--tv-text-sm); color: var(--tv-text-secondary); background: none; border: none;
  cursor: pointer; padding: 0; width: fit-content; transition: color 0.15s;
}
.ld-back:hover { color: var(--tv-primary); }

/* Header card */
.ld-header-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius-md); padding: var(--tv-space-5);
  display: flex; flex-direction: column; gap: var(--tv-space-4);
}
.ld-header-card__top {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: var(--tv-space-4); flex-wrap: wrap;
}
.ld-header-card__title-row {
  display: flex; align-items: center; flex-wrap: wrap; gap: var(--tv-space-2); flex: 1;
}
.ld-lesson-title {
  font-size: var(--tv-text-xl); font-weight: var(--tv-font-bold); color: var(--tv-text); margin: 0;
}
.ld-status {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  padding: 3px var(--tv-space-3); border-radius: var(--tv-radius-full); border: 1px solid transparent;
}
.ld-status--scheduled   { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.ld-status--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-status--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-color: var(--tv-neutral-border); }
.ld-status--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.ld-status--trial       { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.ld-status--live        { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-status--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border-color: hsl(24,70%,70%); }
.ld-status--pending     { background: hsl(220,65%,93%); color: hsl(220,52%,38%); border-color: hsl(220,52%,65%); border-style: dashed; }

.ld-badge {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  padding: 2px 6px; border-radius: var(--tv-radius-full); border: 1px solid;
}
.ld-badge--trial { background: var(--tv-purple-soft); color: var(--tv-purple); border-color: var(--tv-purple-border); }
.ld-badge--recur { background: var(--tv-bg-soft); color: var(--tv-text-muted); border-color: var(--tv-border); }

/* Info grid */
.ld-info-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--tv-space-3) var(--tv-space-5);
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
}
.ld-info-item { display: flex; flex-direction: column; gap: 2px; }
.ld-info-item--full { grid-column: 1 / -1; }
.ld-info-label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; }
.ld-info-value { font-size: var(--tv-text-sm); color: var(--tv-text); font-weight: var(--tv-font-medium); }
.ld-info-value--muted { font-weight: normal; color: var(--tv-text-secondary); word-break: break-all; }

/* ── Top two-column layout ── */
.ld-top-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--tv-space-5);
  align-items: stretch;
}
.ld-top-cols--single {
  grid-template-columns: 1fr;
}

/* ── Join Panel ── */
.ld-join-panel {
  border-radius: var(--tv-radius-md);
  border: 1px solid var(--tv-border);
  padding: var(--tv-space-5) var(--tv-space-6);
  display: flex;
  flex-direction: column;
  gap: var(--tv-space-4);
  background: var(--tv-bg-card);
}

/* Left column */
.ld-join-col--main {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--tv-space-3);
  flex: 1;
}

/* Right column: tips */
.ld-join-col--tips {
  background: var(--tv-bg-soft);
  border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius);
  padding: var(--tv-space-4);
}

/* State variants */
.ld-join-panel--upcoming {
  background: var(--tv-bg-soft);
}
.ld-join-panel--soon {
  background: hsl(38, 95%, 96%);
  border-color: hsl(38, 70%, 80%);
}
.ld-join-panel--joinable {
  background: linear-gradient(135deg, hsl(var(--tv-primary-h), 60%, 97%) 0%, var(--tv-bg-card) 100%);
  border-color: var(--tv-primary-muted);
  box-shadow: 0 2px 12px hsla(var(--tv-primary-h), var(--tv-primary-s), 50%, 0.1);
}
.ld-join-panel--live {
  background: linear-gradient(135deg, hsl(148, 60%, 96%) 0%, var(--tv-bg-card) 100%);
  border-color: var(--tv-success-border);
  box-shadow: 0 2px 12px hsla(148, 60%, 40%, 0.12);
}
.ld-join-panel--expired,
.ld-join-panel--pending {
  background: var(--tv-bg-soft);
  border-color: var(--tv-border);
}
.ld-join-panel--completed {
  background: var(--tv-success-soft);
  border-color: var(--tv-success-border);
}

/* Icon */
.ld-join-icon {
  width: 52px; height: 52px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ld-join-icon--upcoming  { background: var(--tv-primary-soft); color: var(--tv-primary); }
.ld-join-icon--soon      { background: hsl(38, 90%, 90%); color: hsl(38, 75%, 35%); }
.ld-join-icon--expired   { background: var(--tv-neutral-soft); color: var(--tv-neutral); }
.ld-join-icon--pending   { background: hsl(220, 65%, 93%); color: hsl(220, 52%, 38%); }
.ld-join-icon--completed { background: var(--tv-success-soft); color: var(--tv-success-fg); border: 1px solid var(--tv-success-border); }

/* Live dot */
.ld-live-dot {
  width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0;
  background: var(--tv-success-fg);
  box-shadow: 0 0 0 0 hsla(148, 60%, 40%, 0.5);
  animation: ld-pulse 1.6s ease-in-out infinite;
}
@keyframes ld-pulse {
  0%   { box-shadow: 0 0 0 0 hsla(148, 60%, 40%, 0.45); }
  70%  { box-shadow: 0 0 0 10px hsla(148, 60%, 40%, 0); }
  100% { box-shadow: 0 0 0 0 hsla(148, 60%, 40%, 0); }
}

/* Body */
.ld-join-body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.ld-join-title {
  font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0;
}
.ld-join-sub {
  font-size: var(--tv-text-sm); color: var(--tv-text-secondary); margin: 0; line-height: 1.5;
}
.ld-join-timer-inline {
  font-size: var(--tv-text-xs); color: var(--tv-text-muted);
}

/* Countdown */
.ld-countdown {
  display: flex; align-items: center; gap: var(--tv-space-2);
  margin: var(--tv-space-2) 0;
}
.ld-countdown--ticking .ld-countdown-val {
  color: hsl(38, 75%, 30%);
}
.ld-countdown-unit { display: flex; flex-direction: column; align-items: center; gap: 1px; }
.ld-countdown-val {
  font-size: 4.5rem; font-weight: var(--tv-font-bold);
  color: var(--tv-primary); line-height: 1; font-variant-numeric: tabular-nums;
  letter-spacing: -0.02em;
}
.ld-countdown-label { font-size: 10px; color: var(--tv-text-muted); font-weight: var(--tv-font-medium); text-transform: uppercase; letter-spacing: .04em; }
.ld-countdown-sep { font-size: var(--tv-text-xl); font-weight: var(--tv-font-bold); color: var(--tv-text-muted); margin-bottom: 12px; }

/* Actions */
.ld-join-actions { display: flex; align-items: center; justify-content: center; gap: var(--tv-space-3); flex-wrap: wrap; }

/* CTA button */
.ld-join-cta {
  display: inline-flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-6);
  background: var(--tv-primary); color: var(--tv-text-inverse);
  border-radius: var(--tv-radius); font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold);
  text-decoration: none; border: none; cursor: pointer;
  transition: background 0.15s, box-shadow 0.15s; white-space: nowrap;
  box-shadow: 0 3px 12px hsla(var(--tv-primary-h), var(--tv-primary-s), 40%, 0.32);
}
.ld-join-cta:hover { background: var(--tv-primary-hover); box-shadow: 0 4px 16px hsla(var(--tv-primary-h), var(--tv-primary-s), 40%, 0.4); }

.ld-join-cta--live {
  background: var(--tv-success-fg);
  box-shadow: 0 3px 12px hsla(148, 60%, 35%, 0.32);
}
.ld-join-cta--live:hover { background: hsl(148, 52%, 32%); }

/* Tips toggle */
.ld-tips-toggle {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  color: var(--tv-text-muted); background: none; border: none; cursor: pointer;
  padding: var(--tv-space-1) 0; white-space: nowrap; transition: color 0.15s;
}
.ld-tips-toggle svg { transition: transform 0.2s; }
.ld-tips-toggle:hover { color: var(--tv-primary); }

/* Technical tips */
.ld-tips {
  width: 100%;
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  margin-top: var(--tv-space-2);
}
.ld-tips__heading {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em;
  margin: 0 0 var(--tv-space-2);
}
.ld-tips__list {
  list-style: none; padding: 0; margin: 0;
  display: flex; flex-direction: column; gap: var(--tv-space-2);
}
.ld-tips__list li {
  font-size: var(--tv-text-sm); color: var(--tv-text-secondary); line-height: 1.5;
  padding-left: var(--tv-space-4); position: relative;
}
.ld-tips__list li::before {
  content: '✓'; position: absolute; left: 0;
  color: var(--tv-primary); font-weight: var(--tv-font-bold);
}

/* Jump to notes button */
.ld-jump-notes-btn {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  color: var(--tv-success-fg); background: white;
  border: 1px solid var(--tv-success-border);
  border-radius: var(--tv-radius-sm); padding: var(--tv-space-2) var(--tv-space-4);
  cursor: pointer; transition: background 0.15s; white-space: nowrap;
  flex-shrink: 0;
}
.ld-jump-notes-btn:hover { background: var(--tv-success-soft); }

/* Live indicator */
.ld-live-indicator {
  display: flex; align-items: center; justify-content: center; gap: var(--tv-space-2);
}
.ld-live-label {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold);
  color: var(--tv-success-fg); text-transform: uppercase; letter-spacing: .06em;
}

/* Dev state tester bar */
.ld-dev-bar {
  display: flex; align-items: center; gap: var(--tv-space-3); flex-wrap: wrap;
  padding-top: var(--tv-space-3);
  border-top: 1px dashed var(--tv-border);
  margin-top: auto;
  width: 100%;
}
.ld-dev-bar__label {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em;
  white-space: nowrap; flex-shrink: 0;
}
.ld-dev-bar__pills {
  display: flex; flex-wrap: wrap; gap: var(--tv-space-1);
}
.ld-dev-pill {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-medium);
  padding: 3px 10px; border-radius: var(--tv-radius-full);
  border: 1px solid var(--tv-border); background: var(--tv-bg-soft);
  color: var(--tv-text-muted); cursor: pointer; transition: all 0.15s;
  white-space: nowrap;
}
.ld-dev-pill:hover { border-color: var(--tv-primary-muted); color: var(--tv-primary); background: var(--tv-primary-soft); }
.ld-dev-pill--active { background: var(--tv-primary); color: var(--tv-text-inverse); border-color: var(--tv-primary); }

/* Notice banner */
.ld-notice {
  display: flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-3) var(--tv-space-4);
  border-radius: var(--tv-radius); font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
}
.ld-notice--cancelled   { background: var(--tv-neutral-soft); color: var(--tv-neutral); border: 1px solid var(--tv-neutral-border); }
.ld-notice--missed      { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border: 1px solid var(--tv-danger-border); }
.ld-notice--rescheduled { background: hsl(24,88%,93%); color: hsl(24,75%,35%); border: 1px solid hsl(24,70%,70%); }
.ld-notice--completed   { background: var(--tv-success-soft); color: var(--tv-success-fg); border: 1px solid var(--tv-success-border); }

/* Tabs */
.ld-tabs {
  display: flex; gap: 0;
  border-bottom: 2px solid var(--tv-border);
  overflow-x: auto;
  scrollbar-width: none;
}
.ld-tabs::-webkit-scrollbar { display: none; }
.ld-tab {
  display: inline-flex; align-items: center; gap: var(--tv-space-2);
  padding: var(--tv-space-2) var(--tv-space-4);
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  color: var(--tv-text-muted); background: none; border: none; border-bottom: 2px solid transparent;
  margin-bottom: -2px; cursor: pointer; transition: color 0.15s;
  white-space: nowrap;
}
.ld-tab:hover { color: var(--tv-text); }
.ld-tab--active { color: var(--tv-primary); border-bottom-color: var(--tv-primary); }
.ld-tab__count {
  font-size: 10px; background: var(--tv-bg-soft); color: var(--tv-text-muted);
  border: 1px solid var(--tv-border); border-radius: var(--tv-radius-full);
  padding: 0 5px; min-width: 18px; text-align: center; line-height: 16px;
}
.ld-tab--active .ld-tab__count { background: var(--tv-primary-soft); color: var(--tv-primary); border-color: var(--tv-primary-muted); }

/* Tab body */
.ld-tab-body { min-height: 200px; }

/* Section */
.ld-section { display: flex; flex-direction: column; gap: var(--tv-space-4); }
.ld-section__header { display: flex; align-items: center; justify-content: space-between; }
.ld-section__title { font-size: var(--tv-text-base); font-weight: var(--tv-font-semibold); color: var(--tv-text); margin: 0; }

.ld-add-btn {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium);
  color: var(--tv-primary); background: var(--tv-primary-soft);
  border: 1px solid var(--tv-primary-muted); border-radius: var(--tv-radius-sm);
  padding: var(--tv-space-1) var(--tv-space-3); cursor: pointer; transition: background 0.15s;
}
.ld-add-btn:hover { background: hsl(var(--tv-primary-h), 70%, 90%); }

.ld-empty { color: var(--tv-text-muted); font-size: var(--tv-text-sm); margin: 0; padding: var(--tv-space-4) 0; }

/* Notes */
.ld-note-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-note-form__public { display: flex; align-items: center; }
.ld-note-form__actions { display: flex; gap: var(--tv-space-2); justify-content: flex-end; }

.ld-notes-list { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.ld-note-item {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
}
.ld-note-item__header {
  display: flex; align-items: center; gap: var(--tv-space-2); flex-wrap: wrap;
  margin-bottom: var(--tv-space-2);
}
.ld-note-item__author { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); }
.ld-note-item__date   { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.ld-note-item__private {
  font-size: 10px; font-weight: var(--tv-font-semibold);
  background: hsl(38,90%,93%); color: hsl(38,75%,35%);
  border: 1px solid hsl(38,70%,75%); border-radius: var(--tv-radius-full);
  padding: 1px 6px;
}
.ld-note-item__actions { margin-left: auto; display: flex; gap: var(--tv-space-1); }
.ld-note-item__body { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; margin: 0; white-space: pre-wrap; }

/* Attendance */
.ld-attendance-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-attendance-row { display: flex; align-items: center; gap: var(--tv-space-3); }
.ld-attendance-who { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text-secondary); width: 60px; }
.ld-attendance-status {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold);
  padding: 2px var(--tv-space-3); border-radius: var(--tv-radius-full); border: 1px solid;
}
.ld-attendance-status--present       { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-attendance-status--absent        { background: var(--tv-danger-soft); color: var(--tv-danger-fg); border-color: var(--tv-danger-border); }
.ld-attendance-status--absent-notice { background: hsl(30,85%,92%); color: hsl(25,75%,35%); border-color: hsl(30,70%,75%); }
.ld-attendance-status--late          { background: hsl(38,88%,93%); color: hsl(38,75%,35%); border-color: hsl(38,70%,70%); }
.ld-attendance-status--excused       { background: var(--tv-neutral-soft); color: var(--tv-neutral); border-color: var(--tv-neutral-border); }
.ld-attendance-status--rescheduled   { background: hsl(270,50%,93%); color: hsl(270,55%,40%); border-color: hsl(270,45%,78%); }
.ld-attendance-status--default       { background: var(--tv-bg-soft); color: var(--tv-text-muted); border-color: var(--tv-border); }
.ld-attendance-override-notice {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: var(--tv-text-xs);
  color: hsl(30, 75%, 35%);
  background: hsl(38, 90%, 93%);
  border: 1px solid hsl(38, 75%, 75%);
  border-radius: var(--tv-radius);
  padding: 6px 10px;
  margin-bottom: var(--tv-space-2);
}
.ld-attendance-reason { display: flex; flex-direction: column; gap: 2px; padding-top: var(--tv-space-2); border-top: 1px solid var(--tv-border); }
.ld-attendance-reason__label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; }
.ld-attendance-reason__text  { font-size: var(--tv-text-sm); color: var(--tv-text); }
.ld-attendance-meta { font-size: var(--tv-text-xs); color: var(--tv-text-muted); margin: 0; }
.ld-attendance-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}

/* Homework */
.ld-hw-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-hw-list { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.ld-hw-item {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-2);
}
.ld-hw-item__header { display: flex; align-items: center; gap: var(--tv-space-3); flex-wrap: wrap; }
.ld-hw-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-semibold); color: var(--tv-text); flex: 1; }
.ld-hw-status {
  font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold);
  padding: 2px var(--tv-space-2); border-radius: var(--tv-radius-full); border: 1px solid;
}
.ld-hw-status--pending   { background: hsl(38,88%,93%); color: hsl(38,75%,35%); border-color: hsl(38,70%,70%); }
.ld-hw-status--submitted { background: var(--tv-primary-soft); color: hsl(var(--tv-primary-h), var(--tv-primary-s), 38%); border-color: var(--tv-primary-muted); }
.ld-hw-status--reviewed  { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-hw-item__desc { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; margin: 0; }
.ld-hw-item__meta { display: flex; gap: var(--tv-space-4); font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.ld-hw-feedback {
  background: var(--tv-success-soft); border: 1px solid var(--tv-success-border);
  border-radius: var(--tv-radius-sm); padding: var(--tv-space-3);
  display: flex; flex-direction: column; gap: 4px;
}
.ld-hw-feedback__label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-success-fg); text-transform: uppercase; letter-spacing: .05em; }
.ld-hw-feedback__text  { font-size: var(--tv-text-sm); color: var(--tv-text); margin: 0; line-height: 1.5; }
.ld-hw-grade {
  font-size: var(--tv-text-sm); font-weight: var(--tv-font-bold);
  color: var(--tv-success-fg); align-self: flex-start;
  background: white; border: 1px solid var(--tv-success-border);
  border-radius: var(--tv-radius-sm); padding: 1px 8px;
}
.ld-hw-submission { font-size: var(--tv-text-xs); }

/* Materials */
.ld-mat-form {
  background: var(--tv-bg-soft); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-4);
  display: flex; flex-direction: column; gap: var(--tv-space-3);
}
.ld-mat-list { display: flex; flex-direction: column; gap: var(--tv-space-2); }
.ld-mat-item {
  display: flex; align-items: center; gap: var(--tv-space-3);
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-3) var(--tv-space-4);
}
.ld-mat-type-icon { font-size: 20px; flex-shrink: 0; }
.ld-mat-item__body { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.ld-mat-item__title { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-primary); text-decoration: none; }
.ld-mat-item__title:hover { text-decoration: underline; }
.ld-mat-item__meta { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }

/* Shared form elements */
.ld-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: var(--tv-space-3); }
.ld-form-actions { display: flex; gap: var(--tv-space-2); justify-content: flex-end; }
.ld-field { display: flex; flex-direction: column; gap: var(--tv-space-1); }
.ld-label { font-size: var(--tv-text-sm); font-weight: var(--tv-font-medium); color: var(--tv-text); }
.ld-input {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  min-height: 42px; outline: none; transition: border-color 0.15s; font-family: inherit;
}
.ld-input:focus { border-color: var(--tv-primary); }
.ld-textarea {
  padding: var(--tv-space-2) var(--tv-space-3); font-size: var(--tv-text-sm);
  color: var(--tv-text); background: var(--tv-bg-card);
  border: 1.5px solid var(--tv-border); border-radius: var(--tv-radius);
  resize: vertical; outline: none; transition: border-color 0.15s;
  font-family: inherit; line-height: 1.5;
}
.ld-textarea:focus { border-color: var(--tv-primary); }

/* Checkbox */
.ld-checkbox { display: flex; align-items: center; gap: var(--tv-space-2); cursor: pointer; font-size: var(--tv-text-sm); color: var(--tv-text); }
.ld-checkbox__input { position: absolute; opacity: 0; width: 0; height: 0; }
.ld-checkbox__box {
  width: 16px; height: 16px; border: 1.5px solid var(--tv-border);
  border-radius: 3px; background: var(--tv-bg-card); flex-shrink: 0;
  transition: background 0.15s, border-color 0.15s;
}
.ld-checkbox__input:checked + .ld-checkbox__box { background: var(--tv-primary); border-color: var(--tv-primary); }

/* Buttons */
.ld-btn {
  display: inline-flex; align-items: center; gap: var(--tv-space-1);
  padding: var(--tv-space-2) var(--tv-space-4); font-size: var(--tv-text-sm);
  font-weight: var(--tv-font-medium); border-radius: var(--tv-radius-sm);
  border: 1px solid transparent; cursor: pointer; transition: background 0.15s;
  font-family: inherit;
}
.ld-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.ld-btn--primary { background: var(--tv-primary); color: var(--tv-text-inverse); }
.ld-btn--primary:hover:not(:disabled) { background: var(--tv-primary-hover); }
.ld-btn--ghost { background: transparent; border-color: var(--tv-border); color: var(--tv-text-secondary); }
.ld-btn--ghost:hover:not(:disabled) { background: var(--tv-bg-soft); }

/* Icon buttons */
.ld-icon-btn {
  width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
  background: transparent; border: 1px solid var(--tv-border); border-radius: var(--tv-radius-sm);
  color: var(--tv-text-secondary); cursor: pointer; transition: background 0.15s;
}
.ld-icon-btn:hover { background: var(--tv-bg-soft); }
.ld-icon-btn--danger { border-color: var(--tv-danger-border); color: var(--tv-danger-fg); }
.ld-icon-btn--danger:hover { background: var(--tv-danger-soft); }

/* Link */
.ld-link { color: var(--tv-primary); font-size: var(--tv-text-sm); text-decoration: none; }
.ld-link:hover { text-decoration: underline; }

/* Not found */
.ld-not-found {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: var(--tv-space-4); padding: var(--tv-space-8); color: var(--tv-text-muted);
}

@media (max-width: 900px) {
  .ld-top-cols { grid-template-columns: 1fr; }
}

@media (max-width: 600px) {
  .ld-form-row { grid-template-columns: 1fr; }
  .ld-info-grid { grid-template-columns: 1fr 1fr; }
  .ld-join-panel { padding: var(--tv-space-4); }
  .ld-countdown-val { font-size: 2rem; }
}

/* Student Profile Tab */
.ld-student-profile-card {
  background: var(--tv-bg-card); border: 1px solid var(--tv-border);
  border-radius: var(--tv-radius); padding: var(--tv-space-5);
  display: flex; flex-direction: column; gap: var(--tv-space-4);
}
.ld-profile-info-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--tv-space-3) var(--tv-space-5);
}
.ld-profile-info-item { display: flex; flex-direction: column; gap: 2px; }
.ld-profile-info-item--full { grid-column: 1 / -1; }
.ld-profile-info-label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; }
.ld-profile-info-value { font-size: var(--tv-text-sm); color: var(--tv-text); font-weight: var(--tv-font-medium); }
.ld-profile-info-text  { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; margin: 0; }

/* Note read view */
.ld-note-read { display: flex; flex-direction: column; gap: var(--tv-space-4); }
.ld-note-status-row { display: flex; align-items: center; gap: var(--tv-space-3); flex-wrap: wrap; }
.ld-note-status { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); padding: 2px 10px; border-radius: var(--tv-radius-full); border: 1px solid; }
.ld-note-status--submitted { background: var(--tv-success-soft); color: var(--tv-success-fg); border-color: var(--tv-success-border); }
.ld-note-status--draft { background: hsl(38,88%,93%); color: hsl(38,70%,32%); border-color: hsl(38,70%,70%); }
.ld-note-date { font-size: var(--tv-text-xs); color: var(--tv-text-muted); }
.ld-note-fields { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--tv-space-4); }
.ld-note-field { display: flex; flex-direction: column; gap: 4px; }
.ld-note-field--full { grid-column: 1 / -1; }
.ld-note-field__label { font-size: var(--tv-text-xs); font-weight: var(--tv-font-semibold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; }
.ld-note-field__value { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; white-space: pre-wrap; margin: 0; }
.ld-note-internal { background: hsl(38,88%,95%); border: 1px solid hsl(38,70%,78%); border-radius: var(--tv-radius); padding: var(--tv-space-4); display: flex; flex-direction: column; gap: var(--tv-space-2); }
.ld-note-internal__heading { font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold); color: hsl(38,70%,32%); text-transform: uppercase; letter-spacing: .06em; display: flex; align-items: center; gap: var(--tv-space-2); }
.ld-note-internal__body { font-size: var(--tv-text-sm); color: var(--tv-text); line-height: 1.6; white-space: pre-wrap; margin: 0; }

/* Note form sections */
.ld-note-form-section { display: flex; flex-direction: column; gap: var(--tv-space-3); }
.ld-note-form-section__heading { font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold); color: var(--tv-text-muted); text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid var(--tv-border); padding-bottom: var(--tv-space-2); margin: 0; }
.ld-note-internal-form { background: hsl(38,88%,95%); border: 1px solid hsl(38,70%,78%); border-radius: var(--tv-radius); padding: var(--tv-space-4); display: flex; flex-direction: column; gap: var(--tv-space-2); }
.ld-note-internal-form__heading { font-size: var(--tv-text-xs); font-weight: var(--tv-font-bold); color: hsl(38,70%,32%); text-transform: uppercase; letter-spacing: .06em; margin: 0; }

/* Pending dot on tab */
.ld-tab__pending-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--tv-danger-fg); flex-shrink: 0; }
</style>
