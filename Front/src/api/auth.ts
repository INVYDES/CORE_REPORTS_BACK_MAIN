import client from './client'

export const login = (email:string,password:string) => client.post('/login',{email,password}).then(r=>r.data)
export const register = (payload:any) => client.post('/register',payload).then(r=>r.data)
export const me = () => client.get('/me').then(r=>r.data)
export const logout = () => client.post('/logout').then(r=>r.data)