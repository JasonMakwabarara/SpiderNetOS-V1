from fastapi import FastAPI
from pydantic import BaseModel
import openai
import os
from tenacity import retry, stop_after_attempt, wait_exponential

app = FastAPI()

class ChatRequest(BaseModel):
    messages: list
    model: str = "gemma4:2b"
    tenant_id: int

@retry(stop=stop_after_attempt(3), wait=wait_exponential(multiplier=1, min=2, max=10))
def call_ollama(messages):
    import httpx
    return "AI response from local model"

@retry(stop=stop_after_attempt(2), wait=wait_exponential(multiplier=1, min=1, max=5))
def call_openai(messages):
    openai.api_key = os.getenv("OPENAI_API_KEY", "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")
    response = openai.ChatCompletion.create(model="gpt-4o-mini", messages=messages)
    return response.choices[0].message.content

@app.post("/v1/chat/completions")
async def chat(req: ChatRequest):
    try:
        response = call_ollama(req.messages)
    except Exception:
        response = call_openai(req.messages)
    return {"choices": [{"message": {"content": response}}]}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=9000)
