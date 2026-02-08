
import React, { useState } from 'react';
import { Mail, ShieldCheck, Lock, Eye, EyeOff, Globe, ArrowLeft, RefreshCw } from 'lucide-react';

interface Props {
  onBack: () => void;
  theme: 'light' | 'dark';
}

const ForgotPassword: React.FC<Props> = ({ onBack, theme }) => {
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  const handleReset = (e: React.FormEvent) => {
    e.preventDefault();
    setIsLoading(true);
    setTimeout(() => {
      setIsLoading(false);
      onBack();
    }, 1500);
  };

  return (
    <div className="min-h-screen bg-white flex flex-col p-8 max-w-md mx-auto">
      <div className="flex-1 flex flex-col pt-12">
        <div className="mb-10">
          <h1 className="text-3xl font-black text-slate-900 tracking-tight text-center">重置密码</h1>
          <p className="text-slate-400 text-sm font-medium mt-2 italic text-center">找回您的安全访问凭据</p>
        </div>

        <form onSubmit={handleReset} className="space-y-4">
          {/* Email */}
          <div className="space-y-1">
            <input 
              type="email" 
              placeholder="邮箱"
              className="w-full border border-slate-200 rounded-lg py-3 px-4 outline-none focus:border-emerald-500 text-slate-700 font-medium placeholder-slate-400 transition-all"
              required
            />
          </div>

          {/* Email Verification */}
          <div className="flex space-x-0 border border-slate-200 rounded-lg overflow-hidden focus-within:border-emerald-500 transition-all">
            <input 
              type="text" 
              placeholder="邮箱验证码"
              className="flex-1 py-3 px-4 outline-none text-slate-700 font-medium placeholder-slate-400"
              required
            />
            <button 
              type="button" 
              className="bg-slate-700 hover:bg-slate-800 text-white font-bold px-6 transition-colors text-sm"
            >
              发送
            </button>
          </div>

          {/* Password */}
          <div className="relative">
            <input 
              type={showPassword ? 'text' : 'password'} 
              placeholder="密码"
              className="w-full border border-slate-200 rounded-lg py-3 px-4 pr-12 outline-none focus:border-emerald-500 text-slate-700 font-medium placeholder-slate-400 transition-all"
              required
            />
            <button 
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-300 hover:text-slate-500 transition-colors"
            >
              {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
            </button>
          </div>

          {/* Confirm Password */}
          <div className="relative">
            <input 
              type={showConfirmPassword ? 'text' : 'password'} 
              placeholder="再次输入密码"
              className="w-full border border-slate-200 rounded-lg py-3 px-4 pr-12 outline-none focus:border-emerald-500 text-slate-700 font-medium placeholder-slate-400 transition-all"
              required
            />
            <button 
              type="button"
              onClick={() => setShowConfirmPassword(!showConfirmPassword)}
              className="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-300 hover:text-slate-500 transition-colors"
            >
              {showConfirmPassword ? <EyeOff size={18} /> : <Eye size={18} />}
            </button>
          </div>

          <button 
            type="submit"
            disabled={isLoading}
            className="w-full bg-slate-700 hover:bg-slate-800 text-white font-black py-3.5 rounded-lg active:scale-95 transition-all mt-6 flex items-center justify-center space-x-2"
          >
            {isLoading ? <RefreshCw size={20} className="animate-spin" /> : <RefreshCw size={20} />}
            <span>重置密码</span>
          </button>
        </form>

        <div className="mt-12 flex justify-between items-center text-sm font-medium">
          <button onClick={onBack} className="text-slate-500 hover:text-slate-900 flex items-center space-x-1 underline decoration-slate-200">
            <span>返回登入</span>
          </button>
          <div className="flex items-center space-x-2 text-slate-500">
            <Globe size={18} />
            <span>简体中文</span>
          </div>
        </div>
      </div>
    </div>
  );
};

export default ForgotPassword;
