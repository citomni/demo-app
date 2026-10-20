-- Apply once to an existing database whose demo_articles table predates this change. The status
-- check then compares bytes, so a status with a trailing space, such as 'draft ', is refused like
-- any other value than 'draft' and 'published'. The statement fails while a stored row breaks the
-- new check, and it changes no value. Such rows show with this query:
--   SELECT id, status FROM demo_articles WHERE CAST(status AS BINARY) NOT IN ('draft', 'published')
-- DDL implicitly commits. The order relative to deploying the code does not matter. MySQL 8.0.16
-- to 8.0.18 take DROP CHECK instead of DROP CONSTRAINT. A fresh install imports
-- sql/citomni_demo-app.sql instead.
ALTER TABLE demo_articles
	DROP CONSTRAINT chk_demo_articles_status,
	ADD CONSTRAINT chk_demo_articles_status CHECK (CAST(status AS BINARY) IN ('draft', 'published'));
