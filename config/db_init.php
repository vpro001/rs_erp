<?php
// config/db_init.php
// Master Database Initializer & Migration Runner for Royal Solutions ERP

function init_database($conn)
{
  // 1. Table Definitions Array - Updated to match Prisma schema exactly
  $tables = [
    "users_student" => "CREATE TABLE IF NOT EXISTS users_student (
          id VARCHAR(36) PRIMARY KEY,
          email VARCHAR(191) UNIQUE NOT NULL,
          username VARCHAR(191) UNIQUE NULL,
          first_name VARCHAR(100) NOT NULL,
          last_name VARCHAR(100) NOT NULL,
          phone VARCHAR(20) NULL,
          avatar VARCHAR(255) NULL,
          status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION') DEFAULT 'ACTIVE',
          role ENUM('STUDENT') DEFAULT 'STUDENT',
          referral_code VARCHAR(50) UNIQUE NULL,
          last_active DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          email_verified TINYINT(1) DEFAULT 0,
          password_hash VARCHAR(255) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "users_client" => "CREATE TABLE IF NOT EXISTS users_client (
          id VARCHAR(36) PRIMARY KEY,
          email VARCHAR(191) UNIQUE NOT NULL,
          username VARCHAR(191) UNIQUE NULL,
          first_name VARCHAR(100) NOT NULL,
          last_name VARCHAR(100) NOT NULL,
          phone VARCHAR(20) NULL,
          avatar VARCHAR(255) NULL,
          status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION') DEFAULT 'ACTIVE',
          role ENUM('CLIENT') DEFAULT 'CLIENT',
          referral_code VARCHAR(50) UNIQUE NULL,
          last_active DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          email_verified TINYINT(1) DEFAULT 0,
          password_hash VARCHAR(255) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "users_staff" => "CREATE TABLE IF NOT EXISTS users_staff (
          id VARCHAR(36) PRIMARY KEY,
          email VARCHAR(191) UNIQUE NOT NULL,
          username VARCHAR(191) UNIQUE NULL,
          first_name VARCHAR(100) NOT NULL,
          last_name VARCHAR(100) NOT NULL,
          phone VARCHAR(20) NULL,
          avatar VARCHAR(255) NULL,
          status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION') DEFAULT 'ACTIVE',
          role ENUM('STAFF') DEFAULT 'STAFF',
          referral_code VARCHAR(50) UNIQUE NULL,
          last_active DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          email_verified TINYINT(1) DEFAULT 0,
          password_hash VARCHAR(255) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "users_admin" => "CREATE TABLE IF NOT EXISTS users_admin (
          id VARCHAR(36) PRIMARY KEY,
          email VARCHAR(191) UNIQUE NOT NULL,
          username VARCHAR(191) UNIQUE NULL,
          first_name VARCHAR(100) NOT NULL,
          last_name VARCHAR(100) NOT NULL,
          phone VARCHAR(20) NULL,
          avatar VARCHAR(255) NULL,
          status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION') DEFAULT 'ACTIVE',
          role ENUM('ADMIN', 'MANAGER') DEFAULT 'ADMIN',
          referral_code VARCHAR(50) UNIQUE NULL,
          last_active DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          email_verified TINYINT(1) DEFAULT 0,
          password_hash VARCHAR(255) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "profiles" => "CREATE TABLE IF NOT EXISTS profiles (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) UNIQUE NOT NULL,
          user_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN') NOT NULL,
          full_name VARCHAR(200) NULL,
          bio TEXT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_user_type (user_id, user_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "user_settings" => "CREATE TABLE IF NOT EXISTS user_settings (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) UNIQUE NOT NULL,
          user_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN') NOT NULL,
          language VARCHAR(50) DEFAULT 'english',
          timezone VARCHAR(100) DEFAULT 'Africa/Lagos',
          email_notifications TINYINT(1) DEFAULT 1,
          sms_notifications TINYINT(1) DEFAULT 0,
          push_notifications TINYINT(1) DEFAULT 1,
          assignment_reminders TINYINT(1) DEFAULT 1,
          grade_alerts TINYINT(1) DEFAULT 1,
          class_reminders TINYINT(1) DEFAULT 1,
          announcements TINYINT(1) DEFAULT 1,
          marketing_emails TINYINT(1) DEFAULT 0,
          profile_visibility VARCHAR(50) DEFAULT 'public',
          show_email TINYINT(1) DEFAULT 0,
          show_phone TINYINT(1) DEFAULT 0,
          data_sharing TINYINT(1) DEFAULT 1,
          analytics TINYINT(1) DEFAULT 1,
          two_factor_enabled TINYINT(1) DEFAULT 0,
          login_alerts TINYINT(1) DEFAULT 1,
          session_timeout VARCHAR(50) DEFAULT '30',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_user_type (user_id, user_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "balances" => "CREATE TABLE IF NOT EXISTS balances (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) NOT NULL,
          user_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN') NOT NULL,
          amount DECIMAL(15,2) DEFAULT 0.00,
          currency VARCHAR(10) DEFAULT 'USD',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX idx_user_type (user_id, user_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "sessions" => "CREATE TABLE IF NOT EXISTS sessions (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) NOT NULL,
          user_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN') NOT NULL,
          token VARCHAR(255) UNIQUE NOT NULL,
          expires_at DATETIME NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user_type (user_id, user_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "activities" => "CREATE TABLE IF NOT EXISTS activities (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) NOT NULL,
          user_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN') NOT NULL,
          action VARCHAR(255) NOT NULL,
          details JSON NULL,
          ip_address VARCHAR(45) NULL,
          user_agent VARCHAR(255) NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user_type (user_id, user_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "notifications" => "CREATE TABLE IF NOT EXISTS notifications (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) NOT NULL,
          user_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN') NOT NULL,
          title VARCHAR(255) NOT NULL,
          message TEXT NOT NULL,
          type VARCHAR(50) DEFAULT 'GENERAL',
          priority ENUM('LOW', 'MEDIUM', 'HIGH') DEFAULT 'MEDIUM',
          is_read TINYINT(1) DEFAULT 0,
          archived TINYINT(1) DEFAULT 0,
          link VARCHAR(255) NULL,
          metadata JSON NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_user_type (user_id, user_type),
          INDEX idx_user_read (user_id, user_type, is_read),
          INDEX idx_user_created (user_id, user_type, created_at),
          INDEX idx_user_archived (user_id, user_type, archived)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "students" => "CREATE TABLE IF NOT EXISTS students (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) UNIQUE NOT NULL,
          student_id VARCHAR(50) UNIQUE NOT NULL,
          enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
          preferred_time VARCHAR(100) NULL,
          lecture_mode ENUM('ONLINE', 'OFFLINE', 'HYBRID') DEFAULT 'ONLINE',
          payment_method VARCHAR(50) NULL,
          profile_complete TINYINT(1) DEFAULT 0,
          FOREIGN KEY (user_id) REFERENCES users_student(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "clients" => "CREATE TABLE IF NOT EXISTS clients (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) UNIQUE NOT NULL,
          client_id VARCHAR(50) UNIQUE NOT NULL,
          company_name VARCHAR(200) NULL,
          contact_person VARCHAR(200) NULL,
          website VARCHAR(255) NULL,
          industry VARCHAR(100) NULL,
          FOREIGN KEY (user_id) REFERENCES users_client(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "staff" => "CREATE TABLE IF NOT EXISTS staff (
          id VARCHAR(36) PRIMARY KEY,
          user_id VARCHAR(36) UNIQUE NOT NULL,
          staff_id VARCHAR(50) UNIQUE NOT NULL,
          employee_id VARCHAR(50) UNIQUE NOT NULL,
          department VARCHAR(100) NOT NULL,
          position VARCHAR(100) NOT NULL,
          hire_date DATETIME NOT NULL,
          salary DECIMAL(15,2) NULL,
          status ENUM('ACTIVE', 'INACTIVE', 'ON_LEAVE', 'TERMINATED') DEFAULT 'ACTIVE',
          FOREIGN KEY (user_id) REFERENCES users_staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "staff_updates" => "CREATE TABLE IF NOT EXISTS staff_updates (
          id VARCHAR(36) PRIMARY KEY,
          staff_id VARCHAR(36) NOT NULL,
          type ENUM('ASSESSMENT', 'QUERY', 'PROMOTION', 'TERMINATION', 'LEAVE', 'TRAINING') NOT NULL,
          title VARCHAR(200) NOT NULL,
          description TEXT NOT NULL,
          date DATETIME DEFAULT CURRENT_TIMESTAMP,
          created_by VARCHAR(100) NOT NULL,
          FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "performance_reviews" => "CREATE TABLE IF NOT EXISTS performance_reviews (
          id VARCHAR(36) PRIMARY KEY,
          staff_id VARCHAR(36) NOT NULL,
          review_period VARCHAR(100) NOT NULL,
          rating INT DEFAULT 1,
          feedback TEXT NULL,
          goals TEXT NULL,
          review_date DATETIME DEFAULT CURRENT_TIMESTAMP,
          reviewed_by VARCHAR(100) NOT NULL,
          FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "course_categories" => "CREATE TABLE IF NOT EXISTS course_categories (
          id VARCHAR(36) PRIMARY KEY,
          name VARCHAR(191) UNIQUE NOT NULL,
          description TEXT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "courses" => "CREATE TABLE IF NOT EXISTS courses (
          id VARCHAR(36) PRIMARY KEY,
          title VARCHAR(191) NOT NULL,
          description TEXT NULL,
          thumbnail VARCHAR(255) NULL,
          price DECIMAL(15,2) NOT NULL,
          duration VARCHAR(100) NOT NULL,
          level ENUM('BEGINNER', 'INTERMEDIATE', 'ADVANCED') DEFAULT 'BEGINNER',
          mode ENUM('ONLINE', 'OFFLINE', 'HYBRID') DEFAULT 'ONLINE',
          status ENUM('ACTIVE', 'INACTIVE', 'DRAFT', 'ARCHIVED') DEFAULT 'ACTIVE',
          max_enrollment INT NULL,
          allow_installment TINYINT(1) DEFAULT 0,
          installment_count INT NULL,
          installment_amount DECIMAL(15,2) NULL,
          instructor_id VARCHAR(36) NULL,
          category_id VARCHAR(36) NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (instructor_id) REFERENCES staff(id) ON DELETE SET NULL,
          FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "modules" => "CREATE TABLE IF NOT EXISTS modules (
          id VARCHAR(36) PRIMARY KEY,
          course_id VARCHAR(36) NOT NULL,
          title VARCHAR(191) NOT NULL,
          description TEXT NULL,
          order_num INT NOT NULL,
          is_active TINYINT(1) DEFAULT 1,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "lessons" => "CREATE TABLE IF NOT EXISTS lessons (
          id VARCHAR(36) PRIMARY KEY,
          module_id VARCHAR(36) NOT NULL,
          title VARCHAR(191) NOT NULL,
          content TEXT NULL,
          video_url VARCHAR(255) NULL,
          duration VARCHAR(50) NULL,
          order_num INT NOT NULL,
          is_free TINYINT(1) DEFAULT 0,
          FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "lesson_completions" => "CREATE TABLE IF NOT EXISTS lesson_completions (
          id VARCHAR(36) PRIMARY KEY,
          student_id VARCHAR(36) NOT NULL,
          lesson_id VARCHAR(36) NOT NULL,
          completed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
          UNIQUE KEY uq_student_lesson (student_id, lesson_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "course_resources" => "CREATE TABLE IF NOT EXISTS course_resources (
          id VARCHAR(36) PRIMARY KEY,
          course_id VARCHAR(36) NOT NULL,
          title VARCHAR(191) NOT NULL,
          type ENUM('VIDEO', 'DOCUMENT', 'PRESENTATION', 'AUDIO', 'LINK', 'OTHER') NOT NULL,
          url VARCHAR(255) NULL,
          file_path VARCHAR(255) NULL,
          size VARCHAR(50) NULL,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "classes" => "CREATE TABLE IF NOT EXISTS classes (
          id VARCHAR(36) PRIMARY KEY,
          course_id VARCHAR(36) NOT NULL,
          name VARCHAR(191) NOT NULL,
          description TEXT NULL,
          start_date DATETIME NULL,
          end_date DATETIME NULL,
          capacity INT NULL,
          status ENUM('ACTIVE', 'INACTIVE', 'COMPLETED', 'CANCELLED') DEFAULT 'ACTIVE',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "class_students" => "CREATE TABLE IF NOT EXISTS class_students (
          id VARCHAR(36) PRIMARY KEY,
          class_id VARCHAR(36) NOT NULL,
          student_id VARCHAR(36) NOT NULL,
          assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          assigned_by VARCHAR(100) NULL,
          status ENUM('ACTIVE', 'SUSPENDED', 'DROPPED', 'COMPLETED') DEFAULT 'ACTIVE',
          FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          UNIQUE KEY uq_class_student (class_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "class_staff" => "CREATE TABLE IF NOT EXISTS class_staff (
          id VARCHAR(36) PRIMARY KEY,
          class_id VARCHAR(36) NOT NULL,
          staff_id VARCHAR(36) NOT NULL,
          role VARCHAR(100) NULL,
          assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
          FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
          UNIQUE KEY uq_class_staff (class_id, staff_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "class_resources" => "CREATE TABLE IF NOT EXISTS class_resources (
          id VARCHAR(36) PRIMARY KEY,
          class_id VARCHAR(36) NOT NULL,
          title VARCHAR(191) NOT NULL,
          description TEXT NULL,
          type ENUM('VIDEO', 'DOCUMENT', 'PRESENTATION', 'AUDIO', 'LINK', 'OTHER') NOT NULL,
          url VARCHAR(255) NULL,
          file_path VARCHAR(255) NULL,
          file_size VARCHAR(50) NULL,
          uploaded_by VARCHAR(36) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
          FOREIGN KEY (uploaded_by) REFERENCES staff(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "enrollments" => "CREATE TABLE IF NOT EXISTS enrollments (
          id VARCHAR(36) PRIMARY KEY,
          student_id VARCHAR(36) NOT NULL,
          course_id VARCHAR(36) NOT NULL,
          enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
          status ENUM('ACTIVE', 'COMPLETED', 'DROPPED', 'SUSPENDED') DEFAULT 'ACTIVE',
          progress INT DEFAULT 0,
          completion_date DATETIME NULL,
          payment_type ENUM('FULL', 'INSTALLMENT') DEFAULT 'FULL',
          total_paid DECIMAL(15,2) DEFAULT 0.00,
          amount_due DECIMAL(15,2) DEFAULT 0.00,
          batch_id VARCHAR(36) NULL,
          start_date DATE NULL,
          end_date DATE NULL,
          admission_sent_at DATETIME NULL,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
          FOREIGN KEY (batch_id) REFERENCES classes(id) ON DELETE SET NULL,
          UNIQUE KEY uq_student_course (student_id, course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "assignments" => "CREATE TABLE IF NOT EXISTS assignments (
          id VARCHAR(36) PRIMARY KEY,
          course_id VARCHAR(36) NOT NULL,
          student_id VARCHAR(36) NOT NULL,
          title VARCHAR(191) NOT NULL,
          description TEXT NULL,
          due_date DATETIME NULL,
          status ENUM('PENDING', 'SUBMITTED', 'GRADED', 'OVERDUE') DEFAULT 'PENDING',
          submission VARCHAR(255) NULL,
          grade INT NULL,
          feedback TEXT NULL,
          submitted_at DATETIME NULL,
          graded_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "attendance" => "CREATE TABLE IF NOT EXISTS attendance (
          id VARCHAR(36) PRIMARY KEY,
          student_id VARCHAR(36) NOT NULL,
          date DATE NOT NULL,
          status ENUM('PRESENT', 'ABSENT', 'LATE', 'EXCUSED') NOT NULL,
          notes TEXT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          UNIQUE KEY uq_student_date (student_id, date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "results" => "CREATE TABLE IF NOT EXISTS results (
          id VARCHAR(36) PRIMARY KEY,
          student_id VARCHAR(36) NOT NULL,
          course_id VARCHAR(36) NOT NULL,
          exam_type VARCHAR(100) NOT NULL,
          score INT NOT NULL,
          max_score INT NOT NULL,
          grade VARCHAR(10) NOT NULL,
          exam_date DATETIME NOT NULL,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "certificates" => "CREATE TABLE IF NOT EXISTS certificates (
          id VARCHAR(36) PRIMARY KEY,
          student_id VARCHAR(36) NOT NULL,
          course_id VARCHAR(36) NOT NULL,
          certificate_id VARCHAR(100) UNIQUE NOT NULL,
          qr_code VARCHAR(100) UNIQUE NOT NULL,
          digital_signature TEXT NOT NULL,
          issue_date DATETIME DEFAULT CURRENT_TIMESTAMP,
          is_valid TINYINT(1) DEFAULT 1,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "projects" => "CREATE TABLE IF NOT EXISTS projects (
          id VARCHAR(36) PRIMARY KEY,
          project_id VARCHAR(50) UNIQUE NOT NULL,
          title VARCHAR(200) NOT NULL,
          description TEXT NULL,
          client_id VARCHAR(36) NOT NULL,
          supervisor_id VARCHAR(36) NULL,
          status ENUM('PLANNING', 'IN_PROGRESS', 'COMPLETED', 'ON_HOLD', 'CANCELLED') DEFAULT 'PLANNING',
          budget DECIMAL(15,2) NOT NULL,
          start_date DATETIME NOT NULL,
          end_date DATETIME NOT NULL,
          progress INT DEFAULT 0,
          category VARCHAR(100) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
          FOREIGN KEY (supervisor_id) REFERENCES staff(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "project_team_members" => "CREATE TABLE IF NOT EXISTS project_team_members (
          id VARCHAR(36) PRIMARY KEY,
          project_id VARCHAR(36) NOT NULL,
          staff_id VARCHAR(36) NOT NULL,
          role VARCHAR(100) NOT NULL,
          join_date DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
          FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE CASCADE,
          UNIQUE KEY uq_project_staff (project_id, staff_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "project_milestones" => "CREATE TABLE IF NOT EXISTS project_milestones (
          id VARCHAR(36) PRIMARY KEY,
          project_id VARCHAR(36) NOT NULL,
          title VARCHAR(200) NOT NULL,
          description TEXT NULL,
          due_date DATETIME NOT NULL,
          completed TINYINT(1) DEFAULT 0,
          completed_at DATETIME NULL,
          FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "project_updates" => "CREATE TABLE IF NOT EXISTS project_updates (
          id VARCHAR(36) PRIMARY KEY,
          project_id VARCHAR(36) NOT NULL,
          author VARCHAR(200) NOT NULL,
          message TEXT NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "proposals" => "CREATE TABLE IF NOT EXISTS proposals (
          id VARCHAR(36) PRIMARY KEY,
          proposal_id VARCHAR(50) UNIQUE NOT NULL,
          title VARCHAR(200) NOT NULL,
          client_id VARCHAR(36) NOT NULL,
          assigned_to VARCHAR(36) NULL,
          status ENUM('DRAFT', 'PENDING', 'APPROVED', 'REJECTED', 'EXPIRED') DEFAULT 'DRAFT',
          amount DECIMAL(15,2) NOT NULL,
          category VARCHAR(100) NOT NULL,
          description TEXT NULL,
          services JSON NOT NULL,
          timeline VARCHAR(100) NOT NULL,
          valid_until DATETIME NOT NULL,
          response_date DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
          FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "payments" => "CREATE TABLE IF NOT EXISTS payments (
          id VARCHAR(36) PRIMARY KEY,
          transaction_id VARCHAR(100) UNIQUE NOT NULL,
          type ENUM('DEPOSIT', 'MILESTONE', 'FINAL', 'COURSE_FEE', 'LATE_FEE') NOT NULL,
          amount DECIMAL(15,2) NOT NULL,
          method ENUM('PAYSTACK', 'CREDIT_CARD', 'BANK_TRANSFER', 'USSD', 'CASH', 'MOBILE_MONEY') NOT NULL,
          status ENUM('PENDING', 'COMPLETED', 'FAILED', 'REFUNDED', 'OVERDUE') DEFAULT 'PENDING',
          description TEXT NULL,
          due_date DATETIME NULL,
          paid_at DATETIME NULL,
          receipt_url VARCHAR(255) NULL,
          paystack_reference VARCHAR(191) UNIQUE NULL,
          paystack_access_code VARCHAR(191) NULL,
          student_id VARCHAR(36) NULL,
          client_id VARCHAR(36) NULL,
          project_id VARCHAR(36) NULL,
          course_id VARCHAR(36) NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
          FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "installment_payments" => "CREATE TABLE IF NOT EXISTS installment_payments (
          id VARCHAR(36) PRIMARY KEY,
          enrollment_id VARCHAR(36) NOT NULL,
          installment_number INT NOT NULL,
          amount DECIMAL(15,2) NOT NULL,
          due_date DATETIME NOT NULL,
          paid_date DATETIME NULL,
          status ENUM('PENDING', 'PAID', 'OVERDUE', 'CANCELLED') DEFAULT 'PENDING',
          paystack_reference VARCHAR(191) UNIQUE NULL,
          paystack_access_code VARCHAR(191) NULL,
          transaction_id VARCHAR(100) NULL,
          reminder_sent TINYINT(1) DEFAULT 0,
          reminder_sent_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (enrollment_id) REFERENCES enrollments(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "income_records" => "CREATE TABLE IF NOT EXISTS income_records (
          id VARCHAR(36) PRIMARY KEY,
          source ENUM('TRAINING', 'SALES', 'SERVICES', 'CONSULTING', 'CERTIFICATION', 'OTHER') NOT NULL,
          amount DECIMAL(15,2) NOT NULL,
          description TEXT NULL,
          date DATETIME DEFAULT CURRENT_TIMESTAMP,
          category VARCHAR(100) NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "expense_records" => "CREATE TABLE IF NOT EXISTS expense_records (
          id VARCHAR(36) PRIMARY KEY,
          category ENUM('SALARIES', 'INFRASTRUCTURE', 'MARKETING', 'OPERATIONS', 'EQUIPMENT', 'UTILITIES', 'OTHER') NOT NULL,
          amount DECIMAL(15,2) NOT NULL,
          description TEXT NULL,
          date DATETIME DEFAULT CURRENT_TIMESTAMP,
          receipt VARCHAR(255) NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "support_tickets" => "CREATE TABLE IF NOT EXISTS support_tickets (
          id VARCHAR(36) PRIMARY KEY,
          ticket_id VARCHAR(50) UNIQUE NOT NULL,
          title VARCHAR(191) NOT NULL,
          description TEXT NOT NULL,
          status ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'ESCALATED') DEFAULT 'OPEN',
          priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
          category VARCHAR(100) NULL,
          student_id VARCHAR(36) NULL,
          client_id VARCHAR(36) NULL,
          assigned_to VARCHAR(36) NULL,
          resolved_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
          FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "ticket_responses" => "CREATE TABLE IF NOT EXISTS ticket_responses (
          id VARCHAR(36) PRIMARY KEY,
          ticket_id VARCHAR(36) NOT NULL,
          author VARCHAR(200) NOT NULL,
          message TEXT NOT NULL,
          is_staff TINYINT(1) DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "complaints" => "CREATE TABLE IF NOT EXISTS complaints (
          id VARCHAR(36) PRIMARY KEY,
          complaint_id VARCHAR(50) UNIQUE NOT NULL,
          customer_id VARCHAR(36) NOT NULL,
          customer_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN', 'MANAGER') NOT NULL,
          title VARCHAR(191) NOT NULL,
          details TEXT NOT NULL,
          status ENUM('OPEN', 'ADDRESSED', 'SORTED', 'CLOSED') DEFAULT 'OPEN',
          priority ENUM('LOW', 'MEDIUM', 'HIGH', 'URGENT') DEFAULT 'MEDIUM',
          assigned_to VARCHAR(36) NULL,
          resolved_at DATETIME NULL,
          resolution TEXT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (assigned_to) REFERENCES staff(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "forum_posts" => "CREATE TABLE IF NOT EXISTS forum_posts (
          id VARCHAR(36) PRIMARY KEY,
          student_id VARCHAR(36) NOT NULL,
          course_id VARCHAR(36) NULL,
          title VARCHAR(200) NOT NULL,
          content TEXT NOT NULL,
          category VARCHAR(100) NULL,
          tags JSON NULL,
          is_pinned TINYINT(1) DEFAULT 0,
          is_resolved TINYINT(1) DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "forum_replies" => "CREATE TABLE IF NOT EXISTS forum_replies (
          id VARCHAR(36) PRIMARY KEY,
          post_id VARCHAR(36) NOT NULL,
          student_id VARCHAR(36) NOT NULL,
          content TEXT NOT NULL,
          is_solution TINYINT(1) DEFAULT 0,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "forum_post_likes" => "CREATE TABLE IF NOT EXISTS forum_post_likes (
          id VARCHAR(36) PRIMARY KEY,
          post_id VARCHAR(36) NOT NULL,
          student_id VARCHAR(36) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          UNIQUE KEY uq_post_student_like (post_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "forum_reply_likes" => "CREATE TABLE IF NOT EXISTS forum_reply_likes (
          id VARCHAR(36) PRIMARY KEY,
          reply_id VARCHAR(36) NOT NULL,
          student_id VARCHAR(36) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (reply_id) REFERENCES forum_replies(id) ON DELETE CASCADE,
          FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
          UNIQUE KEY uq_reply_student_like (reply_id, student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "meetings" => "CREATE TABLE IF NOT EXISTS meetings (
          id VARCHAR(36) PRIMARY KEY,
          title VARCHAR(191) NOT NULL,
          description TEXT NULL,
          client_id VARCHAR(36) NOT NULL,
          staff_id VARCHAR(36) NULL,
          meeting_date DATETIME NOT NULL,
          duration INT NULL,
          location VARCHAR(255) NULL,
          meeting_url VARCHAR(255) NULL,
          status ENUM('REQUESTED', 'SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'RESCHEDULED') DEFAULT 'SCHEDULED',
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
          FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "assessments" => "CREATE TABLE IF NOT EXISTS assessments (
          id VARCHAR(36) PRIMARY KEY,
          client_id VARCHAR(36) NOT NULL,
          assessor_id VARCHAR(36) NULL,
          type VARCHAR(100) NOT NULL,
          questions JSON NOT NULL,
          score INT NULL,
          notes TEXT NULL,
          completed_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
          FOREIGN KEY (assessor_id) REFERENCES staff(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "questionnaires" => "CREATE TABLE IF NOT EXISTS questionnaires (
          id VARCHAR(36) PRIMARY KEY,
          client_id VARCHAR(36) NOT NULL,
          title VARCHAR(191) NOT NULL,
          questions JSON NOT NULL,
          responses JSON NULL,
          completed_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "documents" => "CREATE TABLE IF NOT EXISTS documents (
          id VARCHAR(36) PRIMARY KEY,
          file_name VARCHAR(255) NOT NULL,
          file_path VARCHAR(255) NOT NULL,
          file_size VARCHAR(50) NULL,
          mime_type VARCHAR(100) NULL,
          client_id VARCHAR(36) NULL,
          project_id VARCHAR(36) NULL,
          uploaded_by VARCHAR(100) NOT NULL,
          uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          current_version INT DEFAULT 1,
          FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
          FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

    "document_versions" => "CREATE TABLE IF NOT EXISTS document_versions (
          id VARCHAR(36) PRIMARY KEY,
          document_id VARCHAR(36) NOT NULL,
          version_number INT NOT NULL,
          file_path VARCHAR(255) NOT NULL,
          file_size VARCHAR(50) NULL,
          uploaded_by VARCHAR(100) NOT NULL,
          uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          change_summary VARCHAR(255) NULL,
          FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
  ];

  // Phase 1: Ensure per-role credential tables exist, then backfill from legacy shared table
  foreach (['users_student', 'users_client', 'users_staff', 'users_admin'] as $user_table) {
    $conn->query($tables[$user_table]);
  }
  backfill_user_tables_from_legacy($conn);
  migrate_enrollment_batches($conn);
  migrate_proposal_response_date($conn);
  migrate_project_proposal_link($conn);
  migrate_meeting_requests($conn);
  migrate_marketers($conn);
  migrate_service_categories($conn);
  migrate_clear_stock_avatars($conn);

  // Phase 2: Skip schema/seed work if demo data already present (guarded on admin credential table)
  $check = $conn->query("SELECT COUNT(*) as count FROM users_admin WHERE id = 'u-admin-0001'");
  $result = $check->fetch_assoc();
  if ($result && $result['count'] > 0) {
    return;
  }

  // Phase 3: Create all tables (credential tables already ensured above; IF NOT EXISTS is idempotent)
  $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
  foreach ($tables as $name => $sql) {
    $conn->query($sql);
  }
  $conn->query("SET FOREIGN_KEY_CHECKS = 1;");

  // 2. Seed Default Accounts & Demo Records if users_student table is empty
  $check_users = $conn->query("SELECT COUNT(*) as count FROM users_student");
  $user_count = intval($check_users->fetch_assoc()['count'] ?? 0);

  if ($user_count === 0) {
    seed_default_data($conn);
  }
}

/**
 * Additive migration: class batches (the `classes` table) and per-enrollment
 * start/end dates for installs created before these columns existed.
 * Fresh installs get the columns from the CREATE TABLE above.
 */
function migrate_enrollment_batches($conn)
{
  $table = $conn->query("SHOW TABLES LIKE 'enrollments'");
  if (!$table || $table->num_rows === 0) {
    return;
  }
  $col = $conn->query("SHOW COLUMNS FROM enrollments LIKE 'batch_id'");
  if ($col && $col->num_rows > 0) {
    return;
  }

  $conn->query("ALTER TABLE enrollments
        ADD COLUMN batch_id VARCHAR(36) NULL,
        ADD COLUMN start_date DATE NULL,
        ADD COLUMN end_date DATE NULL,
        ADD COLUMN admission_sent_at DATETIME NULL,
        ADD CONSTRAINT fk_enrollments_batch FOREIGN KEY (batch_id) REFERENCES classes(id) ON DELETE SET NULL");

  // Students already placed in a class for the same course inherit that batch's dates.
  $conn->query("UPDATE enrollments e
        JOIN class_students cs ON cs.student_id = e.student_id
        JOIN classes cl ON cl.id = cs.class_id AND cl.course_id = e.course_id
        SET e.batch_id = cl.id, e.start_date = DATE(cl.start_date), e.end_date = DATE(cl.end_date)
        WHERE e.batch_id IS NULL");
}

/**
 * Additive migration: client/proposals.php records when a client accepts or declines.
 */
function migrate_proposal_response_date($conn)
{
  $table = $conn->query("SHOW TABLES LIKE 'proposals'");
  if (!$table || $table->num_rows === 0) {
    return;
  }
  $col = $conn->query("SHOW COLUMNS FROM proposals LIKE 'response_date'");
  if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE proposals ADD COLUMN response_date DATETIME NULL AFTER valid_until");
  }
}

/**
 * Additive migration: a kicked-off project remembers the proposal it came from, so the
 * admin kickoff queue can hide proposals that already have a project.
 */
function migrate_project_proposal_link($conn)
{
  $table = $conn->query("SHOW TABLES LIKE 'projects'");
  if (!$table || $table->num_rows === 0) {
    return;
  }
  $col = $conn->query("SHOW COLUMNS FROM projects LIKE 'proposal_id'");
  if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE projects ADD COLUMN proposal_id VARCHAR(36) NULL");
    // Projects created before this column existed: link them to the proposal with the same client and title.
    $conn->query("UPDATE projects pr JOIN proposals p ON p.client_id = pr.client_id AND p.title = pr.title
                  SET pr.proposal_id = p.id WHERE pr.proposal_id IS NULL");
  }
}

/**
 * Additive migration: clients now request meetings (status REQUESTED) that an admin confirms or reschedules.
 */
function migrate_meeting_requests($conn)
{
  $col = $conn->query("SHOW COLUMNS FROM meetings LIKE 'status'");
  $row = $col ? $col->fetch_assoc() : null;
  if ($row && strpos($row['Type'], "'REQUESTED'") === false) {
    $conn->query("ALTER TABLE meetings MODIFY COLUMN status ENUM('REQUESTED','SCHEDULED','IN_PROGRESS','COMPLETED','CANCELLED','RESCHEDULED') DEFAULT 'SCHEDULED'");
  }
}

/**
 * Additive migration: marketers bring in clients (via their referral link) and students
 * (submitted by name + email, confirmed by an admin), and earn a commission on each.
 */
function migrate_marketers($conn)
{
  $table = $conn->query("SHOW TABLES LIKE 'users_marketer'");
  if ($table && $table->num_rows > 0) {
    return;
  }
  // Fresh installs reach this before Phase 3 creates the rest of the schema; wait for the next request.
  $clients = $conn->query("SHOW TABLES LIKE 'clients'");
  if (!$clients || $clients->num_rows === 0) {
    return;
  }

  // Marketers get their own credential/notification identity, like every other role family.
  foreach (['profiles', 'user_settings', 'balances', 'sessions', 'activities', 'notifications'] as $t) {
    $conn->query("ALTER TABLE `$t` MODIFY COLUMN user_type ENUM('STUDENT', 'CLIENT', 'STAFF', 'ADMIN', 'MARKETER') NOT NULL");
  }

  $col = $conn->query("SHOW COLUMNS FROM clients LIKE 'referred_by'");
  if ($col && $col->num_rows === 0) {
    $conn->query("ALTER TABLE clients ADD COLUMN referred_by VARCHAR(36) NULL, ADD INDEX idx_clients_referred_by (referred_by)");
  }

  $conn->query("CREATE TABLE IF NOT EXISTS users_marketer (
          id VARCHAR(36) PRIMARY KEY,
          email VARCHAR(191) UNIQUE NOT NULL,
          username VARCHAR(191) UNIQUE NULL,
          first_name VARCHAR(100) NOT NULL,
          last_name VARCHAR(100) NOT NULL,
          phone VARCHAR(20) NULL,
          avatar VARCHAR(255) NULL,
          status ENUM('ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING_VERIFICATION') DEFAULT 'ACTIVE',
          role ENUM('MARKETER') DEFAULT 'MARKETER',
          referral_code VARCHAR(50) UNIQUE NULL,
          project_commission_rate DECIMAL(5,2) DEFAULT 10.00,
          student_commission_rate DECIMAL(5,2) DEFAULT 10.00,
          last_active DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          email_verified TINYINT(1) DEFAULT 0,
          password_hash VARCHAR(255) NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

  $conn->query("CREATE TABLE IF NOT EXISTS marketer_student_referrals (
          id VARCHAR(36) PRIMARY KEY,
          marketer_id VARCHAR(36) NOT NULL,
          full_name VARCHAR(200) NOT NULL,
          email VARCHAR(191) NOT NULL,
          phone VARCHAR(20) NULL,
          course_id VARCHAR(36) NULL,
          note TEXT NULL,
          status ENUM('PENDING', 'CONFIRMED', 'REJECTED') DEFAULT 'PENDING',
          review_note VARCHAR(255) NULL,
          reviewed_by VARCHAR(36) NULL,
          reviewed_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          INDEX idx_referral_email (email),
          FOREIGN KEY (marketer_id) REFERENCES users_marketer(id) ON DELETE CASCADE,
          FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

  // One commission per kicked-off project and per confirmed student referral (UNIQUE ignores NULLs).
  $conn->query("CREATE TABLE IF NOT EXISTS marketer_commissions (
          id VARCHAR(36) PRIMARY KEY,
          marketer_id VARCHAR(36) NOT NULL,
          source ENUM('PROJECT', 'STUDENT') NOT NULL,
          project_id VARCHAR(36) NULL UNIQUE,
          referral_id VARCHAR(36) NULL UNIQUE,
          description VARCHAR(255) NOT NULL,
          base_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
          rate DECIMAL(5,2) NULL,
          amount DECIMAL(15,2) NOT NULL,
          status ENUM('EARNED', 'PAID', 'CANCELLED') DEFAULT 'EARNED',
          payout_ref VARCHAR(100) NULL,
          paid_at DATETIME NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          FOREIGN KEY (marketer_id) REFERENCES users_marketer(id) ON DELETE CASCADE,
          FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
          FOREIGN KEY (referral_id) REFERENCES marketer_student_referrals(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

/**
 * Additive migration: requests made before the service catalog (helpers.php SERVICE_CATALOG)
 * stored the old onboarding labels; fold them into the current service names.
 */
function migrate_service_categories($conn)
{
  $map = [
    'Business website (static / brochure)' => 'Website Design / Revamp',
    'Website revamp / redesign' => 'Website Design / Revamp',
    'E-commerce store' => 'Website Design / Revamp',
    'Web application' => 'Web Application',
    'Mobile app' => 'Mobile App',
    'Something else' => 'Other',
  ];
  $in = "'" . implode("','", array_map([$conn, 'real_escape_string'], array_keys($map))) . "'";
  foreach (['proposals', 'projects'] as $t) {
    $table = $conn->query("SHOW TABLES LIKE '$t'");
    if (!$table || $table->num_rows === 0) {
      continue;
    }
    $stale = $conn->query("SELECT 1 FROM `$t` WHERE category IN ($in) LIMIT 1");
    if (!$stale || $stale->num_rows === 0) {
      continue;
    }
    $stmt = $conn->prepare("UPDATE `$t` SET category = ? WHERE category = ?");
    foreach ($map as $old => $new) {
      $stmt->bind_param("ss", $new, $old);
      $stmt->execute();
    }
    $stmt->close();
  }
}

/**
 * Data migration: demo accounts were seeded with stock Unsplash photos. Clear them
 * so every user shows their own uploaded photo (profile.php) or an initials placeholder.
 */
function migrate_clear_stock_avatars($conn)
{
  foreach (['users_student', 'users_client', 'users_staff', 'users_admin', 'users_marketer'] as $t) {
    $table = $conn->query("SHOW TABLES LIKE '$t'");
    if (!$table || $table->num_rows === 0) {
      continue;
    }
    $conn->query("UPDATE `$t` SET avatar = NULL WHERE avatar LIKE 'https://images.unsplash.com/%'");
  }
}

/**
 * One-time additive migration: copy rows from the legacy shared `users` table
 * into the per-role credential tables. The legacy table is intentionally left
 * untouched until every module has been migrated to the split tables.
 */
function backfill_user_tables_from_legacy($conn)
{
  // Fresh installs have no legacy table - nothing to migrate.
  $legacy = $conn->query("SHOW TABLES LIKE 'users'");
  if (!$legacy || $legacy->num_rows === 0) {
    return;
  }

  $columns = "id, email, username, first_name, last_name, phone, avatar, status, role, referral_code, last_active, email_verified, password_hash, created_at, updated_at";
  $mappings = [
    'users_student' => "role = 'STUDENT'",
    'users_client'  => "role = 'CLIENT'",
    'users_staff'   => "role = 'STAFF'",
    'users_admin'   => "role IN ('ADMIN', 'MANAGER')",
  ];

  foreach ($mappings as $table => $where) {
    $check = $conn->query("SELECT COUNT(*) AS count FROM `$table`");
    $row = $check->fetch_assoc();
    if (intval($row['count'] ?? 0) > 0) {
      continue; // already backfilled
    }
    $conn->query("INSERT INTO `$table` ($columns) SELECT $columns FROM users WHERE $where");
  }
}

function seed_default_data($conn)
{
  // Development mode: Use simple known password WITHOUT hashing
  $demo_password = 'dev123';

  // 1. Core Users - Insert into role-specific tables
  // Admin users
  $admin_users = [
    [
      'id' => 'u-admin-0001',
      'email' => 'admin@royalsolutions.com',
      'username' => 'admin',
      'first_name' => 'Alexander',
      'last_name' => 'Vance',
      'phone' => '+1 (555) 019-2831',
      'avatar' => null
    ],
    [
      'id' => 'u-manager-0001',
      'email' => 'manager@royalsolutions.com',
      'username' => 'manager',
      'first_name' => 'Eleanor',
      'last_name' => 'Sterling',
      'phone' => '+1 (555) 018-9921',
      'avatar' => null
    ]
  ];

  foreach ($admin_users as $u) {
    $stmt = $conn->prepare("INSERT INTO users_admin (id, email, username, first_name, last_name, phone, avatar, status, email_verified, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, ?)");
    $stmt->bind_param("ssssssss", $u['id'], $u['email'], $u['username'], $u['first_name'], $u['last_name'], $u['phone'], $u['avatar'], $demo_password);
    $stmt->execute();
    $stmt->close();

    // Profiles
    $full_name = $u['first_name'] . ' ' . $u['last_name'];
    $bio = "Registered ADMIN at Royal Solutions ERP command platform.";
    $p_id = 'prof-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO profiles (id, user_id, user_type, full_name, bio) VALUES (?, ?, 'ADMIN', ?, ?)");
    $stmt->bind_param("ssss", $p_id, $u['id'], $full_name, $bio);
    $stmt->execute();
    $stmt->close();

    // User Settings
    $s_id = 'set-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO user_settings (id, user_id, user_type, language, timezone) VALUES (?, ?, 'ADMIN', 'english', 'Africa/Lagos')");
    $stmt->bind_param("ss", $s_id, $u['id']);
    $stmt->execute();
    $stmt->close();

    // Balances
    $b_id = 'bal-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO balances (id, user_id, user_type, amount, currency) VALUES (?, ?, 'ADMIN', 0.00, 'USD')");
    $stmt->bind_param("ss", $b_id, $u['id']);
    $stmt->execute();
    $stmt->close();
  }

  // Staff users
  $staff_users = [
    [
      'id' => 'u-staff-0001',
      'email' => 'staff@royalsolutions.com',
      'username' => 'staff_instructor',
      'first_name' => 'Marcus',
      'last_name' => 'Chen',
      'phone' => '+1 (555) 014-4482',
      'avatar' => null
    ]
  ];

  foreach ($staff_users as $u) {
    $stmt = $conn->prepare("INSERT INTO users_staff (id, email, username, first_name, last_name, phone, avatar, status, email_verified, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, ?)");
    $stmt->bind_param("ssssssss", $u['id'], $u['email'], $u['username'], $u['first_name'], $u['last_name'], $u['phone'], $u['avatar'], $demo_password);
    $stmt->execute();
    $stmt->close();

    // Profiles
    $full_name = $u['first_name'] . ' ' . $u['last_name'];
    $bio = "Registered STAFF at Royal Solutions ERP command platform.";
    $p_id = 'prof-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO profiles (id, user_id, user_type, full_name, bio) VALUES (?, ?, 'STAFF', ?, ?)");
    $stmt->bind_param("ssss", $p_id, $u['id'], $full_name, $bio);
    $stmt->execute();
    $stmt->close();

    // User Settings
    $s_id = 'set-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO user_settings (id, user_id, user_type, language, timezone) VALUES (?, ?, 'STAFF', 'english', 'Africa/Lagos')");
    $stmt->bind_param("ss", $s_id, $u['id']);
    $stmt->execute();
    $stmt->close();

    // Balances
    $b_id = 'bal-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO balances (id, user_id, user_type, amount, currency) VALUES (?, ?, 'STAFF', 0.00, 'USD')");
    $stmt->bind_param("ss", $b_id, $u['id']);
    $stmt->execute();
    $stmt->close();
  }

  // Student users
  $student_users = [
    [
      'id' => 'u-student-0001',
      'email' => 'student@royalsolutions.com',
      'username' => 'student_jane',
      'first_name' => 'Jane',
      'last_name' => 'Smith',
      'phone' => '+1 (555) 012-7711',
      'avatar' => null
    ]
  ];

  foreach ($student_users as $u) {
    $stmt = $conn->prepare("INSERT INTO users_student (id, email, username, first_name, last_name, phone, avatar, status, email_verified, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, ?)");
    $stmt->bind_param("ssssssss", $u['id'], $u['email'], $u['username'], $u['first_name'], $u['last_name'], $u['phone'], $u['avatar'], $demo_password);
    $stmt->execute();
    $stmt->close();

    // Profiles
    $full_name = $u['first_name'] . ' ' . $u['last_name'];
    $bio = "Registered STUDENT at Royal Solutions ERP command platform.";
    $p_id = 'prof-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO profiles (id, user_id, user_type, full_name, bio) VALUES (?, ?, 'STUDENT', ?, ?)");
    $stmt->bind_param("ssss", $p_id, $u['id'], $full_name, $bio);
    $stmt->execute();
    $stmt->close();

    // User Settings
    $s_id = 'set-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO user_settings (id, user_id, user_type, language, timezone) VALUES (?, ?, 'STUDENT', 'english', 'Africa/Lagos')");
    $stmt->bind_param("ss", $s_id, $u['id']);
    $stmt->execute();
    $stmt->close();

    // Balances
    $b_id = 'bal-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO balances (id, user_id, user_type, amount, currency) VALUES (?, ?, 'STUDENT', 0.00, 'USD')");
    $stmt->bind_param("ss", $b_id, $u['id']);
    $stmt->execute();
    $stmt->close();
  }

  // Client users
  $client_users = [
    [
      'id' => 'u-client-0001',
      'email' => 'client@royalsolutions.com',
      'username' => 'client_acme',
      'first_name' => 'David',
      'last_name' => 'Miller',
      'phone' => '+1 (555) 016-5590',
      'avatar' => null
    ]
  ];

  foreach ($client_users as $u) {
    $stmt = $conn->prepare("INSERT INTO users_client (id, email, username, first_name, last_name, phone, avatar, status, email_verified, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, 'ACTIVE', 1, ?)");
    $stmt->bind_param("ssssssss", $u['id'], $u['email'], $u['username'], $u['first_name'], $u['last_name'], $u['phone'], $u['avatar'], $demo_password);
    $stmt->execute();
    $stmt->close();

    // Profiles
    $full_name = $u['first_name'] . ' ' . $u['last_name'];
    $bio = "Registered CLIENT at Royal Solutions ERP command platform.";
    $p_id = 'prof-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO profiles (id, user_id, user_type, full_name, bio) VALUES (?, ?, 'CLIENT', ?, ?)");
    $stmt->bind_param("ssss", $p_id, $u['id'], $full_name, $bio);
    $stmt->execute();
    $stmt->close();

    // User Settings
    $s_id = 'set-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO user_settings (id, user_id, user_type, language, timezone) VALUES (?, ?, 'CLIENT', 'english', 'Africa/Lagos')");
    $stmt->bind_param("ss", $s_id, $u['id']);
    $stmt->execute();
    $stmt->close();

    // Balances
    $b_id = 'bal-' . substr($u['id'], 2);
    $stmt = $conn->prepare("INSERT INTO balances (id, user_id, user_type, amount, currency) VALUES (?, ?, 'CLIENT', 0.00, 'USD')");
    $stmt->bind_param("ss", $b_id, $u['id']);
    $stmt->execute();
    $stmt->close();
  }

  // 2. Staff Profile
  $staff_id = 'staff-rec-0001';
  $stmt = $conn->prepare("INSERT INTO staff (id, user_id, staff_id, employee_id, department, position, hire_date, salary, status) VALUES (?, 'u-staff-0001', 'STF-1001', 'EMP-5001', 'Software Engineering', 'Lead Software Instructor', '2024-01-15', 7500.00, 'ACTIVE')");
  $stmt->bind_param("s", $staff_id);
  $stmt->execute();
  $stmt->close();

  // 3. Student Profile
  $student_id = 'stud-rec-0001';
  $stmt = $conn->prepare("INSERT INTO students (id, user_id, student_id, lecture_mode, payment_method, profile_complete) VALUES (?, 'u-student-0001', 'STUD-88210', 'ONLINE', 'PAYSTACK', 1)");
  $stmt->bind_param("s", $student_id);
  $stmt->execute();
  $stmt->close();

  // 4. Client Profile
  $client_id = 'client-rec-0001';
  $stmt = $conn->prepare("INSERT INTO clients (id, user_id, client_id, company_name, contact_person, website, industry) VALUES (?, 'u-client-0001', 'CL-90210', 'Apex Global Technologies', 'David Miller', 'https://apextech.example.com', 'Fintech & Cloud Solutions')");
  $stmt->bind_param("s", $client_id);
  $stmt->execute();
  $stmt->close();

  // 5. Course Categories & Courses
  $cat_web = 'cat-web-0001';
  $cat_cloud = 'cat-cloud-0002';
  $conn->query("INSERT INTO course_categories (id, name, description) VALUES
        ('$cat_web', 'Web Development', 'Full-stack software engineering, modern PHP, and frontend architectures.'),
        ('$cat_cloud', 'Cloud Architecture', 'DevOps, CI/CD pipelines, containerization and enterprise systems.')");

  $course1_id = 'course-fullstack-001';
  $course2_id = 'course-cloud-002';
  $conn->query("INSERT INTO courses (id, title, description, price, duration, level, mode, status, max_enrollment, allow_installment, installment_count, installment_amount, instructor_id, category_id) VALUES
        ('$course1_id', 'Fullstack Web Architecture with Modern PHP & MySQL', 'Master production web application design with procedural and modular PHP, MySQL prepared statements, Tailwind CSS, and RESTful APIs.', 850.00, '12 Weeks', 'INTERMEDIATE', 'ONLINE', 'ACTIVE', 50, 1, 3, 290.00, '$staff_id', '$cat_web'),
        ('$course2_id', 'Enterprise Cloud & DevOps Infrastructure', 'Hands-on enterprise systems deployment, container orchestration, and server security hardening.', 1200.00, '16 Weeks', 'ADVANCED', 'ONLINE', 'ACTIVE', 40, 1, 4, 300.00, '$staff_id', '$cat_cloud')");

  // 6. Modules & Lessons
  $mod1_id = 'mod-001';
  $mod2_id = 'mod-002';
  $conn->query("INSERT INTO modules (id, course_id, title, description, order_num, is_active) VALUES
        ('$mod1_id', '$course1_id', 'Module 1: High-Performance Database Design', 'Schema normalization, indexing, and parameterized query execution.', 1, 1),
        ('$mod2_id', '$course1_id', 'Module 2: Authentication, Security & CSRF Hardening', 'Session token storage, CSRF defense, and XSS sanitization.', 2, 1)");

  $les1_id = 'les-001';
  $les2_id = 'les-002';
  $les3_id = 'les-003';
  $conn->query("INSERT INTO lessons (id, module_id, title, content, video_url, duration, order_num, is_free) VALUES
        ('$les1_id', '$mod1_id', '1.1 Relational Architecture & UTF8MB4 Collation', 'Detailed notes on creating high-performance relational schemas with strict foreign keys and indexed columns.', 'https://www.w3schools.com/html/mov_bbb.mp4', '25 Mins', 1, 1),
        ('$les2_id', '$mod1_id', '1.2 Native Parameterized mysqli Statements', 'Preventing SQL injections via strict type declarations and transaction rollbacks.', 'https://www.w3schools.com/html/mov_bbb.mp4', '35 Mins', 2, 0),
        ('$les3_id', '$mod2_id', '2.1 Cryptographic Session Tokens & CSRF Defense', 'Implementing stateless and session-based CSRF guards for POST endpoints.', 'https://www.w3schools.com/html/mov_bbb.mp4', '30 Mins', 1, 0)");

  // 7. Student Enrollment
  $enr_id = 'enr-0001';
  $conn->query("INSERT INTO enrollments (id, student_id, course_id, status, progress, payment_type, total_paid, amount_due) VALUES
        ('$enr_id', '$student_id', '$course1_id', 'ACTIVE', 33, 'INSTALLMENT', 290.00, 580.00)");

  // Installment Payments
  $ip1_id = 'inst-001';
  $ip2_id = 'inst-002';
  $ip3_id = 'inst-003';
  $conn->query("INSERT INTO installment_payments (id, enrollment_id, installment_number, amount, due_date, paid_date, status, paystack_reference) VALUES
        ('$ip1_id', '$enr_id', 1, 290.00, DATE_SUB(NOW(), INTERVAL 10 DAY), NOW(), 'PAID', 'PAYSTACK_REF_INIT_001'),
        ('$ip2_id', '$enr_id', 2, 290.00, DATE_ADD(NOW(), INTERVAL 2 DAY), NULL, 'PENDING', NULL),
        ('$ip3_id', '$enr_id', 3, 290.00, DATE_ADD(NOW(), INTERVAL 32 DAY), NULL, 'PENDING', NULL)");

  // 8. Assignments
  $as1_id = 'as-001';
  $conn->query("INSERT INTO assignments (id, course_id, student_id, title, description, due_date, status) VALUES
        ('$as1_id', '$course1_id', '$student_id', 'Build a Secure Parameterized Database Wrapper', 'Implement a reusable db_query helper with strict bind_param type checking and transaction controls.', DATE_ADD(NOW(), INTERVAL 5 DAY), 'PENDING')");

  // 9. Classes & Attendance
  $class1_id = 'cls-001';
  $conn->query("INSERT INTO classes (id, course_id, name, description, start_date, end_date, capacity, status) VALUES
        ('$class1_id', '$course1_id', 'Cohort Alpha (Fall 2026)', 'Live weekend coding cohort covering fullstack architectures.', NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY), 30, 'ACTIVE')");

  $conn->query("INSERT INTO class_students (id, class_id, student_id, status) VALUES ('cs-001', '$class1_id', '$student_id', 'ACTIVE')");
  $conn->query("INSERT INTO class_staff (id, class_id, staff_id, role) VALUES ('cst-001', '$class1_id', '$staff_id', 'Lead Instructor')");

  // 10. Proposals & Projects
  $prop1_id = 'prop-001';
  $conn->query("INSERT INTO proposals (id, proposal_id, title, client_id, assigned_to, status, amount, category, description, services, timeline, valid_until) VALUES
        ('$prop1_id', 'PROP-9021', 'Enterprise Fintech Dashboard & Payment Hub', '$client_id', '$staff_id', 'APPROVED', 14500.00, 'Fintech SaaS', 'Comprehensive ERP integration with Paystack, multi-currency wallets, and reporting ledgers.', '[\"Architecture Design\", \"Database Setup\", \"Payment Gateway Hook\", \"Quality Assurance\"]', '8 Weeks', DATE_ADD(NOW(), INTERVAL 14 DAY))");

  $proj1_id = 'proj-001';
  $conn->query("INSERT INTO projects (id, project_id, title, description, client_id, supervisor_id, status, budget, start_date, end_date, progress, category) VALUES
        ('$proj1_id', 'PRJ-5501', 'Enterprise Fintech Dashboard & Payment Hub', 'Production development of client cloud platform.', '$client_id', '$staff_id', 'IN_PROGRESS', 14500.00, NOW(), DATE_ADD(NOW(), INTERVAL 60 DAY), 45, 'Fintech SaaS')");

  $conn->query("INSERT INTO project_milestones (id, project_id, title, description, due_date, completed, completed_at) VALUES
        ('ms-001', '$proj1_id', 'Phase 1: Architecture & UI System', 'Glassmorphism light interface layout.', DATE_SUB(NOW(), INTERVAL 7 DAY), 1, NOW()),
        ('ms-002', '$proj1_id', 'Phase 2: Core Data Models & API Hooks', 'MySQL parameterized query endpoints.', DATE_ADD(NOW(), INTERVAL 14 DAY), 0, NULL)");

  $conn->query("INSERT INTO project_team_members (id, project_id, staff_id, role) VALUES
        ('ptm-001', '$proj1_id', '$staff_id', 'Technical Lead & Supervisor')");

  // 11. Forum Posts & Replies
  $post1_id = 'post-001';
  $conn->query("INSERT INTO forum_posts (id, student_id, course_id, title, content, category, tags, is_pinned, is_resolved) VALUES
        ('$post1_id', '$student_id', '$course1_id', 'Best practice for securing mysqli prepared statements against type casting?', 'When binding decimal currency values in PHP mysqli, should we declare them as \"d\" or format them as strings \"s\" with precise decimal scale?', 'Coding', '[\"PHP\", \"MySQL\", \"Security\"]', 1, 0)");

  // 12. Meetings
  $meet1_id = 'meet-001';
  $conn->query("INSERT INTO meetings (id, title, description, client_id, staff_id, meeting_date, duration, location, status) VALUES
        ('$meet1_id', 'Sprint Review & Architecture Demo', 'Bi-weekly project milestone review with the engineering team.', '$client_id', '$staff_id', DATE_ADD(NOW(), INTERVAL 1 DAY), 45, 'Jitsi Virtual Conference Room', 'SCHEDULED')");

  // 13. Finance Ledger Records
  $conn->query("INSERT INTO income_records (id, source, amount, description, category, date) VALUES
        ('inc-001', 'TRAINING', 290.00, 'Student tuition installment #1: Jane Smith', 'LMS Course Fee', DATE_SUB(NOW(), INTERVAL 10 DAY)),
        ('inc-002', 'SERVICES', 7250.00, '50% Initial project deposit for PRJ-5501', 'CRM Consulting', DATE_SUB(NOW(), INTERVAL 5 DAY))");

  $conn->query("INSERT INTO expense_records (id, category, amount, description, date) VALUES
        ('exp-001', 'INFRASTRUCTURE', 120.00, 'Monthly Cloud Server Hosting & CDN egress', DATE_SUB(NOW(), INTERVAL 15 DAY)),
        ('exp-002', 'OPERATIONS', 85.00, 'SSL Certificates and domain renewals', DATE_SUB(NOW(), INTERVAL 8 DAY))");

  // 14. Notifications
  $conn->query("INSERT INTO notifications (id, user_id, title, message, type, priority, link) VALUES
        ('notif-001', 'u-student-0001', 'Welcome to Royal Solutions ERP', 'Your student account has been activated. Explore your courses and start learning!', 'LMS', 'HIGH', '/student/my_courses.php'),
        ('notif-002', 'u-client-0001', 'Proposal Approved', 'Your sales proposal for Enterprise Fintech Dashboard is approved.', 'CRM', 'HIGH', '/client/projects.php'),
        ('notif-003', 'u-admin-0001', 'System Ready', 'Royal Solutions ERP database initialized successfully with demo accounts.', 'GENERAL', 'MEDIUM', '/admin/index.php')");

  // 15. Verified Demo Certificate
  $cert_sig = hash('sha256', $student_id . $course1_id . 'ROYAL_SIGNATURE_DEMO');
  $conn->query("INSERT INTO certificates (id, student_id, course_id, certificate_id, qr_code, digital_signature, issue_date, is_valid) VALUES
        ('cert-001', '$student_id', '$course1_id', 'CERT-DEMO-001', 'QR-DEMO-001', '$cert_sig', DATE_SUB(NOW(), INTERVAL 3 DAY), 1)");
}