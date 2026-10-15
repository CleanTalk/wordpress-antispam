#!/usr/bin/env python3
"""Resolve the pentest duty user and, unless create_task is false, open a PSC task."""

import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime, timedelta, timezone


ROLE_NAME_DEFAULT = "Pentesting on Duty"
AUTH_URL = "https://api-ctask.cleantalk.org/user_authorize"


class ApiError(Exception):
    pass


def env(name, default=""):
    return os.environ.get(name, default).strip()


def require(name):
    value = env(name)
    if not value:
        raise ApiError(f"Required environment variable is empty: {name}")
    return value


def post(url, fields):
    data = urllib.parse.urlencode(fields).encode("utf-8")
    request = urllib.request.Request(
        url,
        data=data,
        method="POST",
        headers={"Content-Type": "application/x-www-form-urlencoded"},
    )
    try:
        with urllib.request.urlopen(request, timeout=30) as response:
            raw = response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise ApiError(f"HTTP {exc.code} from {url}: {body}") from exc
    except urllib.error.URLError as exc:
        raise ApiError(f"Request to {url} failed: {exc.reason}") from exc
    try:
        return json.loads(raw)
    except json.JSONDecodeError as exc:
        raise ApiError(f"Non-JSON response from {url}: {raw}") from exc


def authorize():
    result = post(
        AUTH_URL,
        {
            "email": require("DOBOARD_USERNAME"),
            "password": require("DOBOARD_PASSWORD"),
        },
    )
    session_id = (result.get("data") or {}).get("session_id")
    if not session_id:
        raise ApiError("Auth failed")
    return session_id


def company_url(route):
    company_id = env("DOBOARD_COMPANY_ID", "1")
    return f"https://api.doboard.com/{company_id}/{route}"


def role_id(session_id, role_name):
    result = post(company_url("role_get"), {"session_id": session_id})
    roles = (result.get("data") or {}).get("roles")
    if not isinstance(roles, list):
        raise ApiError("Roles not found")
    for role in roles:
        if role.get("name") == role_name:
            return role.get("role_id")
    raise ApiError(f"Role not found: {role_name}")


def duty_user_id(session_id, wanted_role_id):
    today = datetime.now(timezone.utc).date()
    result = post(
        company_url("calendar_events_get"),
        {
            "session_id": session_id,
            "period_begin": today.isoformat(),
            "period_end": (today + timedelta(days=1)).isoformat(),
            "role_id": wanted_role_id,
        },
    )
    events = (result.get("data") or {}).get("events")
    if not isinstance(events, list):
        raise ApiError("Events not found")
    for event in events:
        if str(event.get("role_id")) == str(wanted_role_id):
            return event.get("user_id")
    raise ApiError(f"No shift today for role id {wanted_role_id}")


def task_title(plugin_slug, version):
    return f"PSC для {plugin_slug} {version}"


def task_comment(plugin_slug, version):
    repo = env("GITHUB_REPOSITORY")
    download = f"https://downloads.wordpress.org/plugin/{plugin_slug}.{version}.zip"
    lines = [
        f"Релиз {plugin_slug} {version}.",
        f"Архив: {download}",
    ]
    if repo:
        lines.append(f"Репозиторий: https://github.com/{repo}")
    return "<br>".join(lines)


def summary(text):
    print(text)
    path = env("GITHUB_STEP_SUMMARY")
    if path:
        with open(path, "a", encoding="utf-8") as handle:
            handle.write(text + "\n")


def create_task(session_id, user_id, title, comment):
    project_id = require("DOBOARD_PROJECT_ID")
    track_id = require("DOBOARD_TRACK_ID")
    due = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")
    created = post(
        company_url("task_add"),
        {
            "session_id": session_id,
            "name": title,
            "user_id": user_id,
            "project_id": project_id,
            "track_id": track_id,
            "due_date": due,
        },
    )
    task_id = (created.get("data") or {}).get("task_id")
    if not task_id:
        raise ApiError(f"Task add failed: {json.dumps(created, ensure_ascii=False)}")
    commented = post(
        company_url("comment_add"),
        {
            "session_id": session_id,
            "task_id": task_id,
            "comment": comment,
            "project_id": project_id,
        },
    )
    if not (commented.get("data") or {}).get("comment_id"):
        raise ApiError(f"Comment add failed: {json.dumps(commented, ensure_ascii=False)}")
    return task_id


def main():
    version = require("INPUT_VERSION").lstrip("v")
    plugin_slug = require("INPUT_PLUGIN_SLUG")
    role_name = env("INPUT_ROLE_NAME", ROLE_NAME_DEFAULT)
    create = env("INPUT_CREATE_TASK", "false").lower() == "true"
    project_id = require("DOBOARD_PROJECT_ID")
    track_id = require("DOBOARD_TRACK_ID")

    session_id = authorize()
    wanted_role_id = role_id(session_id, role_name)
    user_id = duty_user_id(session_id, wanted_role_id)
    title = task_title(plugin_slug, version)
    comment = task_comment(plugin_slug, version)

    summary("## PSC task")
    summary(f"- Role: {role_name} ({wanted_role_id})")
    summary(f"- Assignee user id: {user_id}")
    summary(f"- Project: {project_id}")
    summary(f"- Track: {track_id}")
    summary(f"- Title: {title}")
    summary(f"- Create task: {str(create).lower()}")

    if not create:
        summary("- Task was not created.")
        return 0

    try:
        task_id = create_task(session_id, user_id, title, comment)
    except ApiError:
        session_id = authorize()
        task_id = create_task(session_id, user_id, title, comment)
    company_id = env("DOBOARD_COMPANY_ID", "1")
    summary(f"- Created task: https://app.doboard.com/{company_id}/task/{task_id}")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except ApiError as exc:
        print(f"::error::{exc}", file=sys.stderr)
        raise SystemExit(1)
