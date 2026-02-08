
import React, { useState } from 'react';
import { Mail, Lock, Eye, EyeOff } from 'lucide-react';
import Logo from './Logo';

interface Props {
  onLogin: () => void;
  theme: 'light' | 'dark';
  onGoToRegister: () => void;
  onGoToForgot: () => void;
}

const Login: React.FC<Props> = ({ onLogin, theme, onGoToRegister, onGoToForgot }) => {
  const [showPassword, setShowPassword] = useState(false);
  const isDark = theme === 'dark';

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    onLogin();
  };

  return (
    <div className={`min-h-screen transition-colors duration-500 flex flex-col p-8 max-w-md mx-auto ${isDark ? 'bg-[#0f172a]' : 'bg-white'}`}>
      <div className="flex-1 flex flex-col justify-center items-center">
        {/* Logo Section */}
        <div className="mb-12 text-center">
          <div className={`w-28 h-28 rounded-[2rem] flex items-center justify-center mb-6 mx-auto transition-all ${
            isDark 
            ? 'bg-slate-800 shadow-2xl shadow-emerald-900/20 border border-slate-700' 
            : 'bg-white shadow-xl shadow-slate-200 border border-slate-50'
          }`}>
             <div className="w-16 h-16">
               <Logo 
                 classNameBody={isDark ? "stroke-white" : "stroke-slate-900"} 
                 classNameRoad={isDark ? "stroke-emerald-500" : "stroke-emerald-500"}
               />
             </div>
          </div>
          <h1 className={`text-3xl font-black tracking-tight transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>大象网络</h1>
          <p className={`${isDark ? 'text-emerald-400' : 'text-emerald-500'} font-bold text-xs uppercase tracking-widest mt-2`}>Elephant Network</p>
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="w-full space-y-4">
          <div className="space-y-1">
            <label className={`text-xs font-bold ml-1 uppercase tracking-wider transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>邮箱地址</label>
            <div className="relative group">
              <div className={`absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors ${isDark ? 'text-slate-600 group-focus-within:text-emerald-400' : 'text-slate-400 group-focus-within:text-emerald-500'}`}>
                <Mail size={18} />
              </div>
              <input 
                type="email" 
                placeholder="请输入您的邮箱"
                className={`w-full border-2 rounded-2xl py-4 pl-12 pr-4 outline-none transition-all font-medium ${
                  isDark 
                  ? 'bg-slate-900 border-slate-900 focus:border-emerald-600 text-slate-200 placeholder-slate-700' 
                  : 'bg-slate-50 border-slate-50 focus:border-emerald-500 focus:bg-white text-slate-700'
                }`}
              />
            </div>
          </div>

          <div className="space-y-1">
            <label className={`text-xs font-bold ml-1 uppercase tracking-wider transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>访问密码</label>
            <div className="relative group">
              <div className={`absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors ${isDark ? 'text-slate-600 group-focus-within:text-emerald-400' : 'text-slate-400 group-focus-within:text-emerald-500'}`}>
                <Lock size={18} />
              </div>
              <input 
                type={showPassword ? 'text' : 'password'} 
                placeholder="请输入登录密码"
                className={`w-full border-2 rounded-2xl py-4 pl-12 pr-12 outline-none transition-all font-medium ${
                  isDark 
                  ? 'bg-slate-900 border-slate-900 focus:border-emerald-600 text-slate-200 placeholder-slate-700' 
                  : 'bg-slate-50 border-slate-50 focus:border-emerald-500 focus:bg-white text-slate-700'
                }`}
              />
              <button 
                type="button"
                onClick={() => setShowPassword(!showPassword)}
                className={`absolute inset-y-0 right-0 pr-4 flex items-center transition-colors ${isDark ? 'text-slate-600 hover:text-slate-400' : 'text-slate-400 hover:text-slate-600'}`}
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
          </div>

          <div className="text-right pb-4">
            <button 
              type="button" 
              onClick={onGoToForgot}
              className={`text-sm font-semibold transition-colors ${isDark ? 'text-emerald-400 hover:text-emerald-300' : 'text-emerald-500 hover:text-emerald-600'}`}
            >
              忘记密码？
            </button>
          </div>

          <button 
            type="submit"
            className={`w-full font-bold py-4 rounded-2xl shadow-lg active:scale-95 transition-all flex items-center justify-center space-x-2 ${
              isDark 
              ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-900/20' 
              : 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-emerald-100'
            }`}
          >
            <span>登 录</span>
          </button>
        </form>
      </div>

      <div className="text-center py-6">
        <p className={`text-sm font-medium transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>
          还没有账号? <button onClick={onGoToRegister} className={`font-bold hover:underline transition-colors ${isDark ? 'text-emerald-400' : 'text-emerald-500'}`}>立即注册</button>
        </p>
      </div>
    </div>
  );
};

export default Login;
