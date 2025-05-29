Working models:
google/gemini-2.5-pro-exp-03-25:free (rate limited)

Good:
qwen/qwen3-32b:free (very good)
anthropic/claude-3.5-haiku
meta-llama/llama-4-scout:free


Possibly - but the don't follow system promot well. 
mistralai/mixtral-8x7b-instruct
mistralai/mistral-7b-instruct:free
qwen/qwen3-4b:free



-------------

Recommended Model Types:
Chat-optimized models (sometimes called “Chat,” “Chatbot,” or “Assistant” in their names)
Examples:
openai/gpt-3.5-turbo
openai/gpt-4o
mistralai/mistral-7b-instruct (despite the name, this is often tuned for both chat and instruct, but check docs)
mistralai/mixtral-8x7b-instruct (again, check docs—some “instruct” models are dual-tuned for chat and instructions, but pure “chat” models are safest for multi-turn dialogue)
google/gemini-pro (if available)
meta-llama/llama-3-70b-chat (if available on OpenRouter)
What to Look For:
Explicitly “chat” or “assistant” models are best for customer service.
If a model is called “instruct,” check the documentation—some are dual-tuned, but many are not.
OpenAI’s GPT-3.5-turbo, GPT-4, and GPT-4o are industry standards for chatbots.
Mistral and Mixtral “instruct” models are sometimes dual-tuned, but “chat” variants are preferable if available.
OpenRouter-Specific Recommendations:
openai/gpt-3.5-turbo
openai/gpt-4o
mistralai/mixtral-8x7b-instruct (if documentation says it’s chat-optimized)
mistralai/mistral-7b-instruct (same caveat)
meta-llama/llama-3-70b-chat (if available)
google/gemini-pro (if available)