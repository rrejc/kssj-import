/*
Created: 22.11.2015
Modified: 3.1.2016
Model: PostgreSQL 9.4
Database: PostgreSQL 9.4
*/


-- Create tables section -------------------------------------------------

-- Table kssj_gesla

CREATE TABLE "kssj_gesla"(
 "id_gesla" BigSerial NOT NULL,
 "id_bes_vrste" Integer NOT NULL,
 "iztocnica" Character varying(200) NOT NULL
)
;

-- Create indexes for table kssj_gesla

CREATE INDEX "kssj_gesla_ix1" ON "kssj_gesla" ("id_bes_vrste")
;

-- Add keys for table kssj_gesla

ALTER TABLE "kssj_gesla" ADD CONSTRAINT "kssj_gesla_pk" PRIMARY KEY ("id_gesla")
;

-- Table kssj_pomeni

CREATE TABLE "kssj_pomeni"(
 "id_pomena" BigSerial NOT NULL,
 "id_gesla" Bigint NOT NULL,
 "zap_st" Integer NOT NULL,
 "indikator" Character varying(400)
)
;

-- Add keys for table kssj_pomeni

ALTER TABLE "kssj_pomeni" ADD CONSTRAINT "kssj_pomeni_pk" PRIMARY KEY ("id_pomena","id_gesla")
;

-- Table kssj_vrste_strukture

CREATE TABLE "kssj_vrste_strukture"(
 "id_vrste_strukture" BigSerial NOT NULL,
 "struktura" Character varying(100) NOT NULL,
 "opis_text" Character varying(400) NOT NULL,
 "opis_html" Character varying(400) NOT NULL
)
;

-- Create indexes for table kssj_vrste_strukture

CREATE UNIQUE INDEX "kssj_vrste_strukture_ix1" ON "kssj_vrste_strukture" ("struktura")
;

-- Add keys for table kssj_vrste_strukture

ALTER TABLE "kssj_vrste_strukture" ADD CONSTRAINT "kssj_strukture_pk" PRIMARY KEY ("id_vrste_strukture")
;

-- Table kssj_kolokacije

CREATE TABLE "kssj_kolokacije"(
 "id_kolokacije" BigSerial NOT NULL,
 "id_gesla" Bigint NOT NULL,
 "id_pomena" Bigint NOT NULL,
 "id_strukture" Bigint NOT NULL,
 "zap_st" Integer NOT NULL,
 "kolokacija" Character varying(4000) NOT NULL,
 "kolokacija_text" Character varying(4000) NOT NULL
)
;

-- Add keys for table kssj_kolokacije

ALTER TABLE "kssj_kolokacije" ADD CONSTRAINT "kssj_kolokacije_pk" PRIMARY KEY ("id_kolokacije","id_strukture","id_pomena","id_gesla")
;

-- Table kssj_zgledi

CREATE TABLE "kssj_zgledi"(
 "id_zgleda" BigSerial NOT NULL,
 "id_gesla" Bigint NOT NULL,
 "id_pomena" Bigint NOT NULL,
 "id_strukture" Bigint NOT NULL,
 "id_kolokacije" Bigint NOT NULL,
 "zap_st" Bigint NOT NULL,
 "zgled" Character varying(4000) NOT NULL,
 "zgled_text" Character varying(4000) NOT NULL,
 "zgled_html" Character varying(4000) NOT NULL
)
;

-- Add keys for table kssj_zgledi

ALTER TABLE "kssj_zgledi" ADD CONSTRAINT "kssj_zgledi_pk" PRIMARY KEY ("id_zgleda","id_kolokacije","id_strukture","id_pomena","id_gesla")
;

-- Table sssj_bes_vrste

CREATE TABLE "sssj_bes_vrste"(
 "id_bes_vrste" Integer NOT NULL,
 "bes_vrsta" Character varying(200) NOT NULL
)
;

-- Add keys for table sssj_bes_vrste

ALTER TABLE "sssj_bes_vrste" ADD CONSTRAINT "sssj_bes_vrste_pk" PRIMARY KEY ("id_bes_vrste")
;

-- Table kssj_strukture

CREATE TABLE "kssj_strukture"(
 "id_strukture" BigSerial NOT NULL,
 "id_gesla" Bigint NOT NULL,
 "id_pomena" Bigint NOT NULL,
 "id_vrste_strukture" Bigint NOT NULL,
 "zap_st" Integer NOT NULL
)
;

-- Create indexes for table kssj_strukture

CREATE INDEX "IX_Relationship2" ON "kssj_strukture" ("id_vrste_strukture")
;

-- Add keys for table kssj_strukture

ALTER TABLE "kssj_strukture" ADD CONSTRAINT "Key1" PRIMARY KEY ("id_strukture","id_pomena","id_gesla")
;

-- Create relationships section ------------------------------------------------- 

ALTER TABLE "kssj_pomeni" ADD CONSTRAINT "kssj_pomeni_fk1" FOREIGN KEY ("id_gesla") REFERENCES "kssj_gesla" ("id_gesla") ON DELETE NO ACTION ON UPDATE NO ACTION
;

ALTER TABLE "kssj_gesla" ADD CONSTRAINT "kssj_gesla_fk1" FOREIGN KEY ("id_bes_vrste") REFERENCES "sssj_bes_vrste" ("id_bes_vrste") ON DELETE NO ACTION ON UPDATE NO ACTION
;

ALTER TABLE "kssj_strukture" ADD CONSTRAINT "kssj_stukture_fk1" FOREIGN KEY ("id_pomena", "id_gesla") REFERENCES "kssj_pomeni" ("id_pomena", "id_gesla") ON DELETE NO ACTION ON UPDATE NO ACTION
;

ALTER TABLE "kssj_strukture" ADD CONSTRAINT "kssj_strukture_fk2" FOREIGN KEY ("id_vrste_strukture") REFERENCES "kssj_vrste_strukture" ("id_vrste_strukture") ON DELETE NO ACTION ON UPDATE NO ACTION
;

ALTER TABLE "kssj_kolokacije" ADD CONSTRAINT "kssj_kolokacije_fk1" FOREIGN KEY ("id_strukture", "id_pomena", "id_gesla") REFERENCES "kssj_strukture" ("id_strukture", "id_pomena", "id_gesla") ON DELETE NO ACTION ON UPDATE NO ACTION
;

ALTER TABLE "kssj_zgledi" ADD CONSTRAINT "kssj_zgledi_fk1" FOREIGN KEY ("id_kolokacije", "id_strukture", "id_pomena", "id_gesla") REFERENCES "kssj_kolokacije" ("id_kolokacije", "id_strukture", "id_pomena", "id_gesla") ON DELETE NO ACTION ON UPDATE NO ACTION
;





