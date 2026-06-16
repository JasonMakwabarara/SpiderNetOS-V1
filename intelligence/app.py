from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI()

class QueryRequest(BaseModel):
    query: str
    tenant_id: int

@app.post("/rag/query")
async def rag_query(req: QueryRequest):
    return {"answer": f"RAG answer for '{req.query}' (tenant {req.tenant_id})"}

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8001)
