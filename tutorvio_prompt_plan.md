# Tutorvio Implementation Prompt Plan

This document provides a sequential series of prompts designed to build the **Tutorvio Learning Management Portal** from scratch. Each prompt is crafted to ensure a modular, scalable, and aesthetically premium result, following the phases identified in the product vision.

## Tech Stack Recommendation
*   **Frontend:** Vite + Vue 3 (Composition API) + TypeScript
*   **State Management:** Pinia
*   **Styling:** Vanilla CSS (Modern CSS variables/modules) or Vuetify (based on user history)
*   **Icons:** Lucide Vue or FontAwesome
*   **Calendar:** FullCalendar or a custom-built lightweight grid

---

## Phase 1: Foundation & Design System

### Prompt 1.1: Project Scaffolding
> "Initialize a new Vite project with Vue 3 and TypeScript in the current directory. Set up a clean folder structure: `/src/components`, `/src/views`, `/src/stores`, `/src/assets`, `/src/styles`, and `/src/composables`. Create a global CSS file `src/styles/main.css` defining a premium, 'calm and professional' color palette (soft blues, clean whites, slate grays). Set up basic routing with `vue-router`."

### Prompt 1.2: Global Design System & UI Components
> "Create a set of core UI components in `/src/components/ui`. These should include:
> 1.  **TVButton**: Premium button with subtle hover transitions and HSL-based colors.
> 2.  **TVInput/TVSelect**: Accessible, clean form elements.
> 3.  **TVCard**: Glassmorphism or soft-shadow containers.
> 4.  **TVBadge**: For status indicators (Scheduled, Completed, etc.).
> Use Vanilla CSS with CSS variables for all styling to maintain a consistent 'Tutorvio' look."

---

## Phase 2: Authentication & Permissions (RBAC)

### Prompt 2.1: Auth Layout & Role Store
> "Implement an Authentication system using Pinia (`src/stores/auth.ts`). Define four distinct roles: `STUDENT`, `TEACHER`, `ADMIN`, and `STAFF`. Create a beautiful, minimalist Login page that handles role-based redirection. Include a 'Forgot Password' flow mockup."

### Prompt 2.2: Permission-Based Navigation
> "Create a `MainLayout.vue` with a sidebar and header. Implement a navigation menu that dynamically shows/hides items based on the user's role:
> *   **Student:** Dashboard, Schedule, My Lessons, Materials, Billing.
> *   **Teacher:** Dashboard, My Classes, Students, Availability, Payroll.
> *   **Admin:** Full access to all modules."

---

## Phase 3: Scheduling & The Booking Engine (The "Heart")

### Prompt 3.1: The Calendar Engine
> "Build a robust, timezone-aware scheduling component. It should support Daily, Weekly, and Monthly views. 
> *   **Requirement:** Admin can see all teacher availability.
> *   **Requirement:** Students can only see their assigned teacher's availability.
> *   **Requirement:** 30-minute trial intervals (blocking 1 hour for the teacher).
> Use a clean, 'calm' UI with color-coded class statuses."

### Prompt 3.2: Slot Management Logic
> "Implement the business logic for teacher availability. Teachers should be able to set their slots. Admin can override. Ensure teachers can only edit/delete slots that are not yet booked. Implement 'Timezone Sync' to ensure students and teachers see the correct local time."

---

## Phase 4: Lesson Management & Attendance

### Prompt 4.1: Lesson Details & Join Flow
> "Create a 'Lesson Detail' view. This is where users join the class (placeholder for Meet/Whiteboard), view lesson notes, and see materials. For students, show 'Credits Remaining'. For teachers, show 'Student Profile' summary."

### Prompt 4.2: Attendance & Progress Records
> "Implement an Attendance tracking module. After a lesson, teachers must mark attendance (Attended, Missed by Student, Missed by Teacher, etc.). This should trigger updates in the student's credit balance and the teacher's payroll record."

---

## Phase 5: Materials, Homework & Documentation

### Prompt 5.1: File Storage & Materials Library
> "Create a Materials module where Admins/Teachers can upload PDFs, videos, and documents. Students should be able to view/download materials assigned to their specific course or lesson."

### Prompt 5.2: Homework & Feedback System
> "Develop a Homework submission system. Teachers assign tasks; students upload work. Teachers can then provide 'Lesson Notes' and 'Homework Feedback' which are archived in the student's 'Progress Tracking' tab."

---

## Phase 6: Billing, Packages & Payroll

### Prompt 6.1: Package & Credit System
> "Build the Billing module for Students. They can view available 'Packages', see their 'Subscription' status, and track 'Remaining Credits'. Admins should be able to manually adjust credits if needed."

### Prompt 6.2: Teacher Payroll & Invoicing
> "Implement the Teacher Payroll dashboard. Calculate earnings based on 'Completed' classes and 'Custom Pay Rates'. Allow teachers to generate an 'Invoice' for the current cutoff period."

---

## Phase 7: Advanced / Later Features

### Prompt 7.1: Internal Chat System
> "Implement a real-time messaging system using WebSockets (or a mock service for now). Allow Students to message Teachers and Admins to message anyone. Support 'Announcements' for school-wide updates."

### Prompt 7.2: Embedded Meet & Whiteboard
> "Integrate an iframe-based meeting system (e.g., Jitsi or a placeholder for Meet.tutorvio.com). Add a basic collaborative whiteboard component for 'Live' teaching sessions."

---

## Final Polish: Reports, Issues & Security

### Prompt 8.1: Issue Tracking & Support
> "Develop a simple ticketing system for students and teachers to report issues (Technical, Billing, Course Material). Admins should have a dashboard to manage, assign, and resolve these tickets."

### Prompt 8.2: Admin Operations Dashboard
> "Create a high-level Reporting dashboard for Admins. Show charts for:
> *   Total classes this month.
> *   Teacher performance ratings.
> *   Revenue vs. Teacher Pay.
> *   Student retention rates."

### Prompt 8.3: Audit Logs & Security
> "Implement a hidden 'Audit Log' for Admins to track all major changes (who changed a class status, who edited a package). Ensure all routes are protected by role-based guards."
