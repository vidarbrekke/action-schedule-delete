# WordPress Customer AI Chatbot: Developer Overview

## Purpose

This WordPress plugin provides an AI-powered chatbot interface for site visitors. Its primary function is to answer user questions based on the website's content, leveraging Retrieval-Augmented Generation (RAG).

## Core Functionality

1.  **Content Indexing:** The plugin scans and indexes selected WordPress content types (Products, Pages, Posts) into a structured format stored in the WordPress options table. This index includes key information like title, URL, type, and processed text content.
2.  **RAG Implementation:** When a user sends a message via the frontend chat widget, the backend:
    *   Retrieves relevant content snippets from the index based on keyword matching and direct title mentions.
    *   Constructs a prompt for a Large Language Model (LLM), injecting the retrieved content as context along with customizable system and site-specific instructions.
    *   Sends the prompt to the configured LLM API (currently OpenRouter).
3.  **Frontend Widget:** A shortcode (`[wcac_chatbot]`) renders a chat interface where users interact with the bot.
4.  **Admin Configuration:** A settings page allows administrators to configure the LLM API key, select content types for indexing, set system/site prompts, define negative keywords (excluded during indexing), and add custom CSS for the widget.

## Technical Goal

The goal is to provide accurate, context-aware answers sourced directly from the site's content, minimizing LLM hallucination by grounding responses in the indexed data. It aims to be a configurable and extensible solution for adding conversational AI support to WordPress/WooCommerce sites. 