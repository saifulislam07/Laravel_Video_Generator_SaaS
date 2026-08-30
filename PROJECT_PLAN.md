# PROJECT_PLAN.md — Cartoon Character + Real Background Video Generator SaaS

## 1. প্রজেক্ট সংক্ষিপ্ত বর্ণনা

একটি Laravel 13 SaaS অ্যাপ্লিকেশন যা ইউজারকে **রিয়েল ব্যাকগ্রাউন্ড ছবির উপর কার্টুন
ক্যারেক্টার বসিয়ে** ছোট ভার্টিক্যাল ভিডিও (Reels/Shorts স্টাইল, ৩০–৪৫ সেকেন্ড)
তৈরি করতে দেয়। তৈরি হওয়া ভিডিও Facebook Reels ও Instagram-এ পোস্ট করার জন্য
উপযোগী (1080×1920, 9:16)।

ভিডিও রেন্ডারিং লোকাল FFmpeg দিয়ে নয় — **Shotstack Edit API** (fallback: Creatomate)
দিয়ে ক্লাউডে হয়। অ্যাপ শুধু টাইমলাইন JSON বানায়, রেন্ডার রিকোয়েস্ট পাঠায়,
স্ট্যাটাস ট্র্যাক করে এবং আউটপুট ভিডিও ইউজারকে ডেলিভার করে।

### মূল ফিচার লিস্ট

- ইমেইল/পাসওয়ার্ড অথেন্টিকেশন (Laravel Breeze — Livewire স্ট্যাক)
- ব্যাকগ্রাউন্ড ইমেজ আপলোড + অটো রিসাইজ/অপটিমাইজ (Reels ফরম্যাট)
- সিস্টেম-ডিফল্ট কার্টুন ক্যারেক্টার লাইব্রেরি + প্রতি ক্যারেক্টারের একাধিক পোজ
  (idle / smiling / surprised ইত্যাদি, transparent PNG)
- প্রজেক্ট → একাধিক সিন; প্রতি সিনে ব্যাকগ্রাউন্ড + ক্যারেক্টার পোজ (position/scale)
  + সংলাপ টেক্সট (ক্যাপশন) + duration
- ড্র্যাগ-অ্যান্ড-ড্রপ সিন বিল্ডার (Alpine.js) ও সিন রি-অর্ডার
- Preview timeline (থাম্বনেইল ভিউ)
- Shotstack payload বিল্ডার (timeline → tracks → clips)
- রেন্ডার রিকোয়েস্ট + ব্যাকগ্রাউন্ড স্ট্যাটাস পোলিং জব + ওয়েবহুক
- রিয়েল-টাইম স্ট্যাটাস আপডেট (Laravel Reverb / Echo)
- ইউজার ড্যাশবোর্ড: প্রজেক্ট লিস্ট, ভিডিও প্লেয়ার, ডাউনলোড, রিট্রাই
- ক্রেডিট / বিলিং সিস্টেম (ফ্রি টিয়ার = ৫ ক্রেডিট) + প্রাইসিং পেজ
  + bKash/SSLCommerz স্ট্রাকচার (স্টাব)
- অ্যাডমিন প্যানেল: ইউজার/ক্রেডিট ম্যানেজমেন্ট, সিস্টেম ক্যারেক্টার CRUD,
  রেন্ডার মডারেশন, স্ট্যাটিস্টিক্স

## 2. টেক স্ট্যাক

| স্তর | টেকনোলজি |
| --- | --- |
| ফ্রেমওয়ার্ক | Laravel 13 (PHP 8.3+) |
| ফ্রন্টএন্ড | Livewire 3 + Volt, Alpine.js, Tailwind CSS, Vite |
| অথ | Laravel Breeze (Livewire স্ট্যাক) |
| ডাটাবেজ | লোকাল: SQLite; প্রোডাকশন: MySQL 8 |
| কিউ | লোকাল: database driver; প্রোডাকশন: Redis + Horizon |
| ব্রডকাস্ট | Laravel Reverb (WebSocket) + Laravel Echo |
| ইমেজ প্রসেসিং | Intervention Image v3 |
| ভিডিও রেন্ডার | Shotstack Edit API (env: stage/production); fallback Creatomate |
| রোল/পারমিশন | spatie/laravel-permission |
| পেমেন্ট | bKash / SSLCommerz (Phase 8-এ স্টাব, পরে লাইভ) |
| টেস্টিং | Pest 3 (Feature + Unit) |
| ডিপ্লয়মেন্ট | Nginx/Apache + PHP-FPM, Supervisor (queue worker + Horizon), Redis |

### প্রয়োজনীয় ENV ভ্যারিয়েবল (পরিকল্পিত)

```
SHOTSTACK_API_KEY=
SHOTSTACK_ENV=stage            # stage | production
SHOTSTACK_TEMPLATE_ID=
SHOTSTACK_WEBHOOK_SECRET=
QUEUE_CONNECTION=database       # প্রোডাকশনে redis
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
```

## 3. ইউজার ফ্লো

```
সাইনআপ / লগইন
      │
      ▼
ড্যাশবোর্ড ── "নতুন প্রজেক্ট" ──► প্রজেক্ট তৈরি (title)
      │                                │
      │                                ▼
      │                     ব্যাকগ্রাউন্ড আপলোড / গ্যালারি থেকে বাছাই
      │                                │
      │                                ▼
      │                     ক্যারেক্টার + পোজ সিলেক্ট (position / scale)
      │                                │
      │                                ▼
      │                     সংলাপ টেক্সট লেখা + সিন duration সেট
      │                                │
      │                                ▼
      │                     একাধিক সিন যোগ + রি-অর্ডার → Preview Timeline
      │                                │
      │                                ▼
      │                     "Render" ─► ক্রেডিট চেক ─► Shotstack POST /render
      │                                │              (১ ক্রেডিট কাটা)
      │                                ▼
      │                     স্ট্যাটাস: queued → rendering → completed / failed
      │                     (CheckRenderStatusJob পোলিং + Reverb broadcast)
      │                                │
      ▼                                ▼
ড্যাশবোর্ডে রিয়েল-টাইম স্ট্যাটাস ◄────────┘
      │
      ▼
completed ─► ভিডিও প্লেয়ার + ডাউনলোড (Shotstack CDN URL)
failed    ─► error message + "আবার চেষ্টা করুন"
```

## 4. ডাটাবেজ স্কিমা (হাই-লেভেল)

| টেবিল | মূল কলাম | রিলেশন |
| --- | --- | --- |
| `users` | + `credits` (int, default 5) | hasMany projects, credit_transactions |
| `projects` | user_id, title, status (draft/rendering/completed/failed) | belongsTo user; hasMany scenes, video_renders |
| `characters` | user_id (nullable = সিস্টেম), name, thumbnail_path, is_public | hasMany poses |
| `character_poses` | character_id, pose_name, image_path (transparent PNG) | belongsTo character |
| `scenes` | project_id, order, background_image_path, dialogue_text, duration_seconds | belongsTo project; belongsToMany poses (pivot) |
| `scene_characters` | scene_id, character_pose_id, position_x, position_y, scale | pivot |
| `video_renders` | project_id, shotstack_render_id, status (queued/rendering/done/failed), output_url, error_message | belongsTo project |
| `credit_transactions` | user_id, amount (+/-), reason, timestamps | belongsTo user |

ইনডেক্স: `users.id`-references (user_id), `projects.user_id`, `projects.status`,
`scenes.project_id`, `video_renders.project_id`, `video_renders.status`।

## 5. ফেজ-ভিত্তিক ডেভেলপমেন্ট রোডম্যাপ

| ফেজ | নাম | ডেলিভারেবল | নির্ভরতা |
| --- | --- | --- | --- |
| 0 | প্ল্যানিং | এই ডকুমেন্ট | — |
| 1 | বেস প্রজেক্ট, অথ, কিউ | Breeze auth, database queue + jobs টেবিল, queue মনিটরিং, `.env.example` placeholders, ড্যাশবোর্ড লেআউট | 0 |
| 2 | ডাটাবেজ স্কিমা | সব মাইগ্রেশন, মডেল, রিলেশন, ইনডেক্স | 1 |
| 3 | অ্যাসেট ম্যানেজমেন্ট | ব্যাকগ্রাউন্ড আপলোড (Livewire), Intervention Image, ডিফল্ট ক্যারেক্টার সিডার, গ্যালারি ভিউ, storage:link | 2 |
| 4 | সিন বিল্ডার | Scene Builder Livewire কম্পোনেন্ট, ড্র্যাগ-ড্রপ প্রিভিউ (Alpine), সিন রি-অর্ডার, Preview Timeline | 3 |
| 5 | Shotstack payload বিল্ডার | `ShotstackPayloadBuilder` সার্ভিস ক্লাস + Pest টেস্ট (1080×1920) | 4 + **Shotstack API key ম্যানুয়ালি নিতে হবে** |
| 6 | রেন্ডার + পোলিং + webhook | `VideoRenderService`, `CheckRenderStatusJob`, Reverb broadcasting, `ProjectRenderStatusUpdated` ইভেন্ট, webhook রুট | 5 |
| 7 | ইউজার ড্যাশবোর্ড + ডেলিভারি | প্রজেক্ট লিস্ট, ভিডিও প্লেয়ার, ডাউনলোড, প্রোগ্রেস ইন্ডিকেটর, রিট্রাই; CDN vs লোকাল স্টোরেজ সিদ্ধান্ত | 6 |
| 8 | ক্রেডিট / বিলিং | `users.credits`, রেন্ডারের আগে ক্রেডিট চেক, `credit_transactions` লগ, bKash/SSLCommerz স্টাব, প্রাইসিং পেজ | 7 |
| 9 | অ্যাডমিন প্যানেল | spatie/laravel-permission, অ্যাডমিন রুট গ্রুপ, ইউজার/ক্রেডিট ম্যানেজমেন্ট, সিস্টেম ক্যারেক্টার CRUD, রেন্ডার মডারেশন, স্ট্যাটস | 8 |
| 10 | টেস্টিং + ডিপ্লয়মেন্ট | Feature টেস্ট (Pest), `.env.production.example`, `DEPLOYMENT.md`, Supervisor কনফিগ উদাহরণ, `README.md` | 9 |

### প্রতি ফেজের সমাপ্তি চেকলিস্ট

1. ফেজের কোড লেখা ও `php artisan migrate` (যদি প্রযোজ্য) সফল
2. সংশ্লিষ্ট টেস্ট / ম্যানুয়াল যাচাই পাস
3. ফেজ সামারি রিভিউ
4. ম্যানুয়াল ব্যাকআপ / স্ন্যাপশট (এই প্রজেক্টে git ব্যবহার হচ্ছে না)

## 6. বর্তমান অবস্থা (Phase 0 সম্পন্ন হওয়ার সময়)

- ✅ **সব ১০টি ফেজ সম্পন্ন** (2026-08-30)। ৯২টি Pest টেস্ট পাস।
- ✅ Laravel 13 + Livewire 3/Volt + Tailwind; Breeze auth; MySQL (`video_generator`)
- ✅ Phase 1–2: queue (database), queue-health widget, স্কিমা (projects/characters/character_poses/scenes/scene_characters/video_renders/background_images)
- ✅ Phase 3–4: অ্যাসেট আপলোড (Intervention v4), সিস্টেম ক্যারেক্টার সিডার, সিন বিল্ডার (Alpine drag + @alpinejs/sort), preview timeline
- ✅ Phase 5–7: ShotstackPayloadBuilder, VideoRenderService + CheckRenderStatusJob + webhook, Reverb broadcasting, ইউজার ড্যাশবোর্ড (player/download/retry)
- ✅ Phase 8–10: ক্রেডিট/বিলিং + stub gateways + pricing, অ্যাডমিন প্যানেল (spatie), DEPLOYMENT.md + deploy/ configs + .env.production.example + README
- ℹ️ Horizon লোকালে ইনস্টল হয়নি (Windows-এ `pcntl`/`posix` নেই) — DEPLOYMENT.md-তে সার্ভারে ইনস্টলের ধাপ আছে

## 7. ঝুঁকি ও সিদ্ধান্ত (পরে বিবেচ্য)

- **Shotstack খরচ**: প্রতি রেন্ডারে API খরচ হয় → ক্রেডিট সিস্টেম (Phase 8) আবশ্যক;
  ডেভেলপমেন্টে `SHOTSTACK_ENV=stage` (ফ্রি, watermark) ব্যবহার
- **ভিডিও স্টোরেজ**: শুরুতে Shotstack CDN URL সরাসরি ব্যবহার (সস্তা ও সহজ);
  পরে চাইলে S3-তে কপি (Phase 7-এ প্রোজ/কনস আলোচনা)
- **ক্যারেক্টার আর্ট**: Phase 3-এ placeholder PNG; আসল কার্টুন আর্ট তৈরি হলে প্রতিস্থাপন
- **Reverb বনাম wire:poll**: Reverb না চললে fallback হিসেবে Livewire `wire:poll`
- **পেমেন্ট গেটওয়ে**: Phase 8-এ শুধু স্ট্রাকচার + UI; লাইভ কানেকশন পরে
- **অ্যাডমিন প্যানেল UI**: প্রম্পটে AdminLTE বলা ছিল, কিন্তু পুরো অ্যাপ Tailwind + Livewire/Volt — তাই কনসিস্টেন্সির জন্য অ্যাডমিন প্যানেলও Tailwind + Volt দিয়ে বানানো হয়েছে (Bootstrap/AdminLTE আনা হয়নি)। রোল/পারমিশন: `spatie/laravel-permission`

## 8. সিদ্ধান্ত: ভিডিও ডেলিভারি — CDN URL বনাম লোকাল কপি (Phase 7)

| | Shotstack CDN URL সরাসরি | লোকাল/S3-তে কপি |
| --- | --- | --- |
| খরচ | ফ্রি (Shotstack হোস্ট করে) | S3 storage + egress খরচ, কপি করার queue জব |
| সেটআপ | শূন্য — `output_url` সরাসরি `<video>`/download-এ | S3 disk কনফিগ + `DownloadRenderJob` + রিট্রাই |
| স্থায়িত্ব | **stage রেন্ডার ~২৪ ঘণ্টা পর মুছে যায়**; production URL দীর্ঘস্থায়ী কিন্তু চিরস্থায়ী নয় | সম্পূর্ণ নিয়ন্ত্রণ, যতদিন চাই |
| ব্র্যান্ডিং/লিংক | shotstack.io ডোমেইন | নিজের ডোমেইন/সাইনড URL |
| ব্যর্থতা | Shotstack ডাউন হলে ভিডিও অ্যাক্সেস হয় না | নিজের স্টোরেজে নিরাপদ |

**সিদ্ধান্ত (এখন): সরাসরি CDN URL।** `video_renders.output_url`-এ Shotstack CDN লিংক রাখা হয়, ড্যাশবোর্ডের HTML5 প্লেয়ার ও ডাউনলোড বাটন ওটাই ব্যবহার করে। কারণ — MVP-তে সস্তা, শূন্য সেটআপ, আর ইউজাররা সাধারণত রেন্ডারের পরপরই ডাউনলোড করে ফেলে।

**পরে যখন দরকার হবে** (ইউজার লাইব্রেরি স্থায়ী রাখতে চাইলে বা production URL expiry সমস্যা হলে): `syncStatus()`-এ done হওয়ার পর একটা `DownloadRenderJob` ডিসপ্যাচ করে `output_url` → S3/`public` disk-এ কপি করে সেই লোকাল URL দিয়ে রিপ্লেস করা। স্কিমা বদলাতে হবে না।
```
