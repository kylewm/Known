--
-- Base Known schema
--

--
-- Table structure for table config
--

CREATE TABLE IF NOT EXISTS config (
  uuid varchar(255) NOT NULL PRIMARY KEY,
  _id varchar(32) NOT NULL,
  jdoc jsonb
);
CREATE INDEX ON config (_id);
CREATE INDEX ON config USING GIN (jdoc jsonb_path_ops);

-- --------------------------------------------------------

--
-- Table structure for table entities
--

CREATE TABLE IF NOT EXISTS entities (
  uuid varchar(255) NOT NULL PRIMARY KEY,
  _id varchar(32) NOT NULL UNIQUE,
  jdoc jsonb
);

CREATE INDEX ON entities (_id);
CREATE INDEX ON entities USING GIN (jdoc jsonb_path_ops);


-- FULL TEXT ?



-- --------------------------------------------------------

--
-- Table structure for table reader
--

CREATE TABLE IF NOT EXISTS reader (
  uuid varchar(255) NOT NULL PRIMARY KEY,
  _id varchar(32) NOT NULL UNIQUE,
  jdoc jsonb
);

CREATE INDEX ON reader (_id);
CREATE INDEX ON reader USING GIN (jdoc jsonb_path_ops);


-- --------------------------------------------------------

--
-- Table structure for table versions
--

CREATE TABLE IF NOT EXISTS versions (
  label varchar(32) NOT NULL PRIMARY KEY,
  value varchar(10) NOT NULL
);

DELETE FROM versions WHERE label = 'schema';
INSERT INTO versions VALUES('schema', '20160220');

--
-- Session handling table
--

CREATE TABLE IF NOT EXISTS session (
    session_id varchar(255) NOT NULL PRIMARY KEY,
    session_value text NOT NULL,
    session_time integer NOT NULL
);
