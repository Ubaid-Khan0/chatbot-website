-- Nexum Chatbot: recommended Supabase/PostgreSQL schema
-- Run this in the Supabase SQL editor after creating a project.

create extension if not exists pgcrypto;

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  display_name text,
  role text not null default 'viewer' check (role in ('admin', 'editor', 'viewer')),
  created_at timestamptz not null default now()
);

create table public.leads (
  id uuid primary key default gen_random_uuid(),
  name text not null,
  company text not null,
  email text not null,
  plan text not null default 'Not sure' check (plan in ('Starter', 'Growth', 'Custom', 'Not sure')),
  message text not null,
  status text not null default 'New' check (status in ('New', 'Contacted', 'Qualified', 'Won', 'Archived')),
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.plans (
  id uuid primary key default gen_random_uuid(),
  slug text not null unique,
  name text not null,
  starting_price_pkr integer not null check (starting_price_pkr >= 0),
  price_suffix text not null default '',
  description text not null,
  features jsonb not null default '[]'::jsonb,
  active boolean not null default true,
  sort_order integer not null default 0,
  updated_at timestamptz not null default now()
);

create table public.site_content (
  key text primary key,
  value jsonb not null,
  updated_at timestamptz not null default now()
);

create table public.audit_log (
  id bigint generated always as identity primary key,
  actor_id uuid references auth.users(id) on delete set null,
  action text not null,
  entity_type text not null,
  entity_id text,
  payload jsonb not null default '{}'::jsonb,
  created_at timestamptz not null default now()
);

create or replace function public.is_admin_or_editor()
returns boolean
language sql
stable
security definer
set search_path = public
as $$
  select exists (
    select 1 from public.profiles
    where id = auth.uid() and role in ('admin', 'editor')
  );
$$;

alter table public.profiles enable row level security;
alter table public.leads enable row level security;
alter table public.plans enable row level security;
alter table public.site_content enable row level security;
alter table public.audit_log enable row level security;

create policy "Users can read their profile"
on public.profiles for select
to authenticated
using (id = auth.uid() or public.is_admin_or_editor());

create policy "Admins manage profiles"
on public.profiles for all
to authenticated
using (public.is_admin_or_editor())
with check (public.is_admin_or_editor());

create policy "Admins manage leads"
on public.leads for all
to authenticated
using (public.is_admin_or_editor())
with check (public.is_admin_or_editor());

create policy "Anyone can read active plans"
on public.plans for select
to anon, authenticated
using (active = true);

create policy "Admins manage plans"
on public.plans for all
to authenticated
using (public.is_admin_or_editor())
with check (public.is_admin_or_editor());

create policy "Anyone can read site content"
on public.site_content for select
to anon, authenticated
using (true);

create policy "Admins manage site content"
on public.site_content for all
to authenticated
using (public.is_admin_or_editor())
with check (public.is_admin_or_editor());

create policy "Admins read audit log"
on public.audit_log for select
to authenticated
using (public.is_admin_or_editor());

insert into public.plans (slug, name, starting_price_pkr, price_suffix, description, features, sort_order)
values
  ('starter', 'Starter', 150000, '', 'A focused chatbot for essential questions and a clear customer journey.', '["One primary journey", "Branded responses", "Essential knowledge"]', 1),
  ('growth', 'Growth', 300000, '', 'Multiple conversation flows, qualification, and selected workflow connections.', '["Multiple journeys", "Lead qualification", "Selected integration"]', 2),
  ('custom', 'Custom', 600000, '+', 'Advanced journeys, larger knowledge sets, and custom integrations.', '["Custom architecture", "Advanced routing", "Multiple integrations"]', 3)
on conflict (slug) do nothing;

-- Do not add an anonymous INSERT policy to leads for production.
-- Submit the public contact form through a rate-limited Supabase Edge Function,
-- validate the payload there, and insert using a server-side service-role secret.
