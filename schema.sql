-- schema.sql — минимальная схема
CREATE TABLE IF NOT EXISTS sections (
  id BIGSERIAL PRIMARY KEY,
  slug TEXT UNIQUE NOT NULL,
  title TEXT NOT NULL,
  sort INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS topics (
  id BIGSERIAL PRIMARY KEY,
  section_id BIGINT NOT NULL REFERENCES sections(id) ON DELETE CASCADE,
  slug TEXT UNIQUE,
  title TEXT NOT NULL,
  tg_chat_id BIGINT NOT NULL,
  tg_thread_id BIGINT NOT NULL,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_topics_section_updated ON topics(section_id, updated_at DESC);
CREATE UNIQUE INDEX IF NOT EXISTS ux_topics_chat_thread ON topics(tg_chat_id, tg_thread_id);

CREATE TABLE IF NOT EXISTS posts (
  id BIGSERIAL PRIMARY KEY,
  topic_id BIGINT NOT NULL REFERENCES topics(id) ON DELETE CASCADE,
  tg_message_id BIGINT NOT NULL,
  author_tg_id BIGINT,
  author_display TEXT,
  content_md TEXT,
  content_html TEXT,
  media_json JSONB,
  created_at TIMESTAMPTZ NOT NULL,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  UNIQUE (topic_id, tg_message_id)
);
CREATE INDEX IF NOT EXISTS idx_posts_topic_created ON posts(topic_id, created_at);

CREATE TABLE IF NOT EXISTS users (
  id BIGSERIAL PRIMARY KEY,
  tg_user_id BIGINT UNIQUE,
  tg_username TEXT,
  avatar_url TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS moderation_actions (
  id BIGSERIAL PRIMARY KEY,
  post_id BIGINT REFERENCES posts(id) ON DELETE SET NULL,
  action TEXT NOT NULL,
  reason TEXT,
  meta_json JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Базовый раздел, чтобы webhook мог писать темы
INSERT INTO sections (slug, title, sort) VALUES ('obshchiy', 'Общий', 0)
ON CONFLICT (slug) DO NOTHING;
