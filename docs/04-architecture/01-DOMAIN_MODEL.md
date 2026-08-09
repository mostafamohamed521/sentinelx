---
title: Domain Model
category: Architecture
status: Approved
version: 2.0

depends_on:
  - BUSINESS_FLOW.md
  - ASES_SPECIFICATION.md

related_documents:
  - DATABASE_DESIGN.md
  - BACKEND_ARCHITECTURE.md

related_diagrams:
  - Domain Model Diagram
---

# Domain Model

> **Baseline v2.0 note:** this document was updated to add the Human Identity layer (`User`), which was designed after the original v1.0 freeze in a dedicated Authentication Design series. See `docs/backend/backend-architecture/adr/ADR-002-human-identity-baseline-update.md` for the full rationale, and `backend/docs/02-auth/` for the complete Authentication design.

## Overview

This document defines the core business entities of SentinelX and the relationships between them.

The Domain Model represents the business language of the platform independently of implementation details.

It is the foundation upon which the database, backend services, and APIs are built.

---

# Design Principles

The Domain Model focuses on business concepts rather than technical implementation.

It intentionally avoids discussing:

- Database tables
- REST endpoints
- Programming languages
- Frameworks

---

# Core Entities

Version 1 consists of the following primary entities.

- Organization
- User
- Agent
- Observation
- Event
- Prediction
- Alert
- API Key

---

# Organization

Represents a customer using SentinelX.

Responsibilities:

- Owns Users.
- Owns Agents.
- Owns Observations.
- Owns Alerts.
- Manages API Keys.

---

# User

Represents a human who authenticates into SentinelX to manage an Organization and view its data.

Responsibilities:

- Authenticates using Email + Password, issued a JWT upon successful login.
- Belongs to exactly one Organization (V1).
- Holds a Role (`Owner`, `Admin`, or `Member`) that determines what actions are authorized.
- The first User created for an Organization is always its `Owner`.

A User is distinct from an Agent: a User is a human observer who manages the platform; an Agent is the AI entity being monitored. See `backend/docs/02-auth/` for the full Authentication and Authorization design.

**V1 scope note:** every Organization is provisioned with exactly one User (its Owner) at registration. Multi-member teams, invited via an Invitation flow, are designed but deferred to a future version — see `backend/docs/02-auth/08-identity-lifecycle.md`.

---

# Agent

Represents an AI Agent registered inside an Organization.

Responsibilities:

- Authenticates using an API Key.
- Produces Observations.
- Executes Tasks outside SentinelX.
- Sends execution data through the SDK.

SentinelX does not execute the Agent itself.

---

# Observation

Represents one completed execution received from an Agent.

An Observation contains:

- Context
- Ordered Events
- Metadata

Observations are immutable after submission.

---

# Event

Represents one execution action inside an Observation.

Examples include:

- API Calls
- File Access
- Command Execution
- Network Activity

Events exist only within an Observation.

---

# Prediction

Represents the security analysis returned by the ML Engine.

A Prediction contains:

- Verdict
- Risk Score
- Confidence
- Summary
- Evidence

Each Observation has exactly one Prediction.

---

# Alert

Represents a notification generated after evaluating a Prediction.

Alerts exist only when platform policies determine that user attention is required.

---

# API Key

Represents the authentication identity of an Agent.

API Keys determine:

- Organization
- Agent

Agent identity is never included inside the Observation payload itself.

---

# Relationships

Organization

↓

owns

↓

User

Organization

↓

owns

↓

Agent

↓

creates

↓

Observation

↓

contains

↓

Events

↓

analyzed by

↓

Prediction

↓

may generate

↓

Alert